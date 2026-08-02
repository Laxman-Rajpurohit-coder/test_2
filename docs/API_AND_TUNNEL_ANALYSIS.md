# API & Tunnel Technical Analysis Specification

Detailed technical specification for MSG91 v5 WhatsApp API integration and Ngrok tunnel architecture.

---

## 📡 1. MSG91 v5 WhatsApp API Analysis

### Base URL & Endpoints
* **Base Domain:** `https://control.msg91.com`
* **Outbound Session Message Endpoint:** `POST /api/v5/whatsapp/whatsapp-outbound-message/`
  *(Note: Crucial v5 spec requires **no `/send`** at the URL path root).*

### Authentication & Headers
MSG91 authenticates outbound requests via request headers:
```http
Content-Type: application/json
authkey: {MSG91_AUTH_KEY}
```

### Request Payload Structure (Free-Text Session Reply)
When sending a free-text response inside a 24-hour active session:
```json
{
  "integrated_number": "917425889008",
  "recipient_number": "918830718466",
  "text": "Hello, how can I help you?",
  "content_type": "text",
  "payload": {
    "to": "918830718466",
    "type": "text",
    "text": {
      "body": "Hello, how can I help you?"
    }
  }
}
```
> [!IMPORTANT]
> **Key Field Rules:**
> 1. `integrated_number` and `recipient_number` must include full country code (e.g., `91...`) with **no leading `+` sign** and no spaces.
> 2. The string `text` must be duplicated both at the root JSON object (`"text": "..."`) and nested inside `"payload" -> "text" -> {"body": "..."}`.

---

## 📥 2. Inbound Webhook Payload & Signature Verification

### Webhook Reception (`POST /msg91/webhook`)
MSG91 pushes inbound customer messages to your registered Webhook URL.

### Native MSG91 Payload Format
```json
{
  "direction": "0",
  "customerNumber": "918830718466",
  "eventName": "received",
  "requestId": "req_65842918",
  "uuid": "wamid.HBgMOTE4ODMwNzE4NDY2...",
  "content": {
    "text": "Can I check my order status?"
  },
  "ts": "1785060659"
}
```

### Signature / Secret Verification Architecture
To protect against unauthenticated write access and fake message injection, every inbound request is validated by `WebhookController`:
```php
$expectedSecret = config('services.msg91.webhook_secret');
$incomingSecret = $request->header('X-MSG91-Secret') ?? $request->header('authkey') ?? $request->query('secret');

if (!empty($expectedSecret) && $expectedSecret !== $incomingSecret) {
    return response()->json(['error' => 'Unauthorized signature'], 401);
}
```

---

## 🚇 3. Ngrok Tunnel Architecture

### Dynamic vs. Static Webhook URLs
* **Dynamic Tunnel (Free Plan):** Executing `ngrok http 80` generates a random domain on every restart (e.g., `https://a1b2-c3d4.ngrok-free.app`). 
  * *Constraint:* Requires manually updating the Webhook URL in MSG91 whenever Ngrok restarts.
* **Static Domain (Paid / Claimed Domain):** Executing `ngrok http --url=anchovy-video-aggregate.ngrok-free.dev 80` preserves a permanent URL.

### Docker Port Forwarding
* **Web Traffic (Port 80):** Maps `host:80` -> `container:80` (nginx/artisan serve).
* **WebSocket Traffic (Port 8080):** Maps `host:8080` -> `container:8080` (Reverb server).
  * *Crucial Setting:* `REVERB_SERVER_HOST="0.0.0.0"` in `.env` is required so Reverb binds to all interfaces, allowing Docker bridge port passthrough to the Windows host machine.
