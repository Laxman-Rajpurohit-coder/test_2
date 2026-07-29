# Project Troubleshooting & Incident Log

---

## 📌 Incident 001: MSG91 Webhook Returning `404 Not Found`

### 🔍 Issue Description
During live MSG91 webhook testing via ngrok, incoming POST requests were failing with HTTP status `404 Not Found`:
```text
POST /api/msg91/webhook 404 Not Found
```

### 🔬 Root Cause
MSG91 dashboard was configured to post events to `/api/msg91/webhook`, whereas Laravel's router in `routes/web.php` was originally listening on `/msg91/webhook` (without the `/api/` prefix).

### 🛠️ Resolution Applied
Added route aliases in `routes/web.php` to handle all potential MSG91 URL variations:
```php
Route::post('/msg91/webhook', [WebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyMsg91Webhook::class);

Route::post('/api/msg91/webhook', [WebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyMsg91Webhook::class);

Route::post('/api/webhooks/msg91', [WebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyMsg91Webhook::class);
```
Cleared route cache using `php artisan route:clear`.

---

## 🔑 Ngrok Domain Lifecycle & Static URL Setup

### ❓ Question: Will the ngrok URL change every time I restart ngrok?
**Yes.** On standard free ngrok sessions, a new random public URL is generated upon every restart (e.g. `https://xxxx.ngrok-free.dev`).

### 💡 Solutions to Keep a Permanent URL:

#### Option 1: Use Ngrok's Free Static Domain (Recommended for Local Dev)
Ngrok provides **1 free static domain** per account.
1. Log into your [ngrok dashboard](https://dashboard.ngrok.com/cloud-edge/domains).
2. Claim your free static domain (e.g., `your-name.ngrok-free.app`).
3. Run ngrok using your static domain flag:
   ```bash
   ngrok http 80 --domain=your-name.ngrok-free.app
   ```
4. Paste `https://your-name.ngrok-free.app/api/msg91/webhook` into MSG91 once, and you **never** have to update MSG91 again when restarting ngrok!

#### Option 2: Production Server Deployment
When deployed to a production VPS or cPanel server (refer to `DEPLOYMENT_GUIDE.md`), your webhooks will run permanently under your custom domain (e.g., `https://wa.yourdomain.com/api/msg91/webhook`).
