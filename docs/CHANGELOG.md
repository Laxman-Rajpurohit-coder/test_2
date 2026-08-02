# Architectural Improvements & Refactoring Changelog

All code optimizations, security guard rails, idempotency enhancements, and structural improvements introduced in this release.

---

## 🔒 1. Security & Guard Rail Improvements
* **Enforced 24-Hour WhatsApp Session Window:**
  * Added session check in `ChatController.php` via `Cache::has('session:'.$customerNumber)`.
  * Free-text messages (`type: 'text'`) attempted outside the 24-hour window are blocked on the backend with an HTTP 422 Unprocessable Entity response before database insertion.
* **Webhook Signature Verification:**
  * Added secret signature validation in `WebhookController.php` against `config('services.msg91.webhook_secret')`.
  * Rejects unauthorized external HTTP POST requests with an HTTP 401 Unauthorized error.
* **Production Config Caching (`env()` Decoupling):**
  * Registered `msg91` credentials in `config/services.php`.
  * Replaced direct `env()` helper calls in controllers and queued jobs with `config()`, enabling production `php artisan config:cache` compatibility.

---

## ⚡ 2. Queue Job Retries & Differentiated Error Handling
* **Differentiated HTTP Error Handling in `SendMsg91Message.php`:**
  * **HTTP 5xx Server Errors / Timeouts:** Throws a PHP `Exception` to trigger automatic Queue Worker retries (`$tries = 3`, `$backoff = [5, 15, 30]`).
  * **HTTP 4xx Client Errors:** Marks database message status as `failed` immediately without retrying (e.g., invalid phone number or bad auth key).

---

## 🛡️ 3. Meta UUID Lifecycle Upserting & Database Integrity
* **Atomic `meta_uuid` Lifecycle Upserts:**
  * Added `2026_07_27_110000_add_unique_meta_uuid_to_whatsapp_messages` migration with a `UNIQUE` constraint on `meta_uuid`.
  * Refactored `ProcessMsg91Webhook.php` to perform `updateOrInsert` based on `meta_uuid`. Status transitions (`sent` -> `delivered` -> `read`) update the **exact same row** instead of creating fragmented duplicate rows.
* **Race-Safe Conversation Upserts:**
  * Replaced non-atomic `where()->first()` conversation queries with `DB::table('conversations')->updateOrInsert(...)` to prevent duplicate conversation creation during high-frequency concurrent webhooks.

---

## 🚀 4. True 0ms Real-Time WebSocket & Sender State Updates
* **Zero-Refetch WebSocket Payload Broadcast:**
  * Updated `MessageReceived` event to carry the complete `$message` payload.
  * Updated `Thread.jsx` WebSocket listener to append or update incoming message payloads directly in local React state in **0ms** without re-querying the backend list API.
* **Sender-Side 0ms Appending:**
  * Updated `Composer.jsx` to pass the returned `$message` object directly to `onSent(newMessage)`. The sender's tab now appends the message immediately in **0ms** without trigger a full API refetch.

---

## 🛠️ 5. Operational Lessons Learned & Queue Worker Daemon Safety
* **Queue Worker Survival Across `queue:restart`:**
  * Executing `php artisan queue:restart` sends a signal telling active `queue:work` processes to exit cleanly. In local Docker environments without a process supervisor (like Supervisor), worker processes remain exited, causing incoming webhooks to accumulate in the `jobs` queue.
  * **Rule:** Always restart the background `queue:work` daemon immediately after calling `queue:restart` or clearing application cache.
* **Cache Persistence for 24-Hour Session Window:**
  * Running `php artisan cache:clear` purges active Redis session keys (`session:{customer_number}`).
  * **Rule:** When running `cache:clear` during active testing, re-establish or trigger an inbound message to ensure free-text outbound messages are not blocked by the session guard rail.
* **Fail-Closed Header Verification (`X-MSG91-Secret`):**
  * `VerifyMsg91Webhook.php` strictly enforces constant-time `hash_equals()` on `$request->header('X-MSG91-Secret')`. All webhooks registered in MSG91 (Inbound & Delivery Status) must pass this header to avoid HTTP 401 Unauthorized rejections.
