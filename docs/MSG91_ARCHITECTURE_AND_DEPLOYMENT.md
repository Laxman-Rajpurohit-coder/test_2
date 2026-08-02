# MSG91 WhatsApp Architecture & Deployment Guide

This document serves as the absolute source of truth for the MSG91 inbound/outbound architecture, database tracking, media handling, and deployment gotchas. Provide this file to any AI assistant to instantly give them the context they need to understand, debug, or extend the WhatsApp integration.

---

## 1. Outbound Messaging Architecture (Sending)

All outbound messages (whether 1-on-1 manual chats or bulk automated campaigns) must flow through a single unified pipeline to guarantee database consistency, error handling, and business rule enforcement.

### The Flow:
1. **Entry Point:** `ChatController@store` (for manual) or `SendCampaignJob` (for campaigns).
2. **Business Rule Guard (`Cache::has('session:'.$phone)`):** WhatsApp enforces a strict 24-hour window for free-text messages. 
   - If `type === 'text'`, we check Redis for `session:CUSTOMER_NUMBER`. If missing, the message is blocked (or fails) unless it is a pre-approved `template`.
3. **Payload Builder:** `\App\Services\Msg91PayloadBuilder::build()`
   - Converts our internal standard into MSG91's specific JSON structure.
   - **CRITICAL FORMATTING RULE:** Customer numbers strictly require a `91` country code prefix. The builder checks `strlen($number) === 10`. If true, it prepends `91`. If already 12 digits (e.g., `917425889008`), it ignores it to prevent fatal double-prefixing (`91917425889008`).
4. **Service Hub:** `\App\Services\OutboundReplyService::send()`
   - Records the message in the `whatsapp_messages` table as `queued`.
   - Fires the `MessageReceived` WebSocket event to update the frontend UI.
   - Dispatches `SendMsg91Message` to the Redis queue.
5. **Execution Job:** `\App\Jobs\SendMsg91Message`
   - Dynamically selects the MSG91 API endpoint:
     - `template` messages go to: `https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/`
     - `text`/`interactive`/`media` messages go to: `https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/`
   - Handles HTTP 5xx errors by retrying (exponential backoff) and HTTP 4xx errors by permanently failing the message in the database.

---

## 2. Inbound Messaging Architecture (Webhooks)

When a customer replies on WhatsApp, MSG91 sends an HTTP POST request to our application.

### The Webhook Payload Shape (MSG91 Format)
```json
{
  "direction": 0,
  "customerNumber": "917425889008",
  "integratedNumber": "918830718466",
  "text": "Hello, I need help!",
  "type": "text",
  "requestId": "cd325bfa-717d-4e86-ba11-74f4c01087f5"
}
```

### The Flow:
1. **Controller (`WebhookController@handle`):** 
   - Receives the payload and immediately dispatches `ProcessMsg91Webhook::dispatch()`. It returns `200 OK` instantly to avoid MSG91's strict 8-second timeout penalty.
2. **Execution Job (`ProcessMsg91Webhook`):**
   - Extracts `integratedNumber` and queries `TenantResolverService` to identify exactly which tenant this message belongs to.
   - **UPSERT:** Uses raw PostgreSQL atomic upsert (`ON CONFLICT`) to create or update the `conversations` table.
   - **DB Logging:** Saves the message to `whatsapp_messages` as `received`.
   - **24-Hour Session Open:** Caches the session (`Cache::put('session:917425889008', true, 24h)`), allowing outbound free-text replies.
   - **Bot Dispatch:** If the message is text or an interactive button reply, it dispatches `ProcessBotTriggerJob` to trigger any automated AI/Flowise workflows.

---

## 3. Media Handling & Local Development Gotchas (The Windows Symlink Issue)

### How Media is Stored:
When an agent uploads an image or records an audio `.ogg` voice note, `ChatController@storeMedia` saves it to:
`storage/app/public/media/{uuid}.ogg`

It saves the URL in the database as: `https://your-domain.com/storage/media/{uuid}.ogg`.

### The Windows Localhost Problem:
Laravel relies on a symlink (`public/storage` -> `storage/app/public`) to serve these files. On Linux/Mac or Production environments (Nginx/Apache), this works flawlessly.

**However, on Windows using `php artisan serve`:**
PHP's built-in web server often fails to resolve this symlink, returning a `404 Not Found` in the frontend when trying to play voice notes, even though the file physically exists.

**The Solution (Already Implemented in `routes/web.php`):**
We injected a fallback route at the top of `web.php` that exclusively intercepts media requests on local Windows environments and streams them manually via PHP, bypassing the symlink bug:
```php
if (app()->environment('local') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    Route::get('/storage/media/{filename}', function ($filename) {
        $path = storage_path('app/public/media/' . $filename);
        if (!file_exists($path)) abort(404);
        return response()->file($path);
    });
}
```
**AI Note:** *Do not remove this block, or local Windows voice notes will 404.*

---

## 4. Deployment Checklist

When pushing to production (Railway, AWS, etc.):

1. **Environment Variables:**
   - Ensure `APP_URL` exactly matches the production domain.
   - Ensure `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis` (critical for webhooks and the 24-hour session window).
2. **Storage Symlink:**
   - During the build step, you **must** run `php artisan storage:link`. Nginx will handle serving the media natively (the Windows fallback route is automatically disabled in production).
3. **Queue Workers:**
   - You must have a persistent background worker running: `php artisan queue:work --sleep=3 --tries=3`. If this dies, inbound webhooks and outbound campaigns will freeze.
4. **WebSockets (Reverb) - CRITICAL:**
   - Ensure `php artisan reverb:start` is running as a daemon.
   - **THE HOSTNAME TRAP:** NEVER set `REVERB_HOST="reverb"` or `"localhost"` in production `.env`. 
     - `"reverb"` triggers DNS resolution failures (`cURL error 6: Could not resolve host`) outside of strict Docker container networks.
     - `"localhost"` can trigger IPv6 `::1` binding failures on Linux servers.
     - **SOLUTION:** Always explicitly set `REVERB_HOST="127.0.0.1"` in the `.env` file so the PHP backend queue workers can successfully POST broadcast payloads to the local Reverb server. 
     - *Note: `VITE_REVERB_HOST` must still be your public domain (`${APP_URL}`) so external browsers can reach it.*
