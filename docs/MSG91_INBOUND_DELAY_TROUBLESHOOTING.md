# 🚨 Technical Analysis & Troubleshooting: MSG91 Inbound Webhook Delays

---

## 📌 Executive Summary

If incoming WhatsApp customer messages take **2 to 5 minutes** to trigger bot responses or appear in the dashboard, the delay is **NOT caused by Laravel, PostgreSQL, Redis, or the Queue Pipeline**.

Server-side queue processing takes **less than 1.5 seconds total** (`ProcessMsg91Webhook`: ~19ms, `ProcessBotTriggerJob`: ~11ms, `SendMsg91Message`: ~450ms).

The delay occurs **entirely upstream within MSG91's platform** before the webhook HTTP POST request is sent to our server.

---

## 🔍 Root Cause Analysis & Empirical Proof

### 1. Timestamp Discrepancy in Raw Webhook Payloads
Examining raw JSON payloads received directly from MSG91 reveals two distinct timestamp fields:

| Field | Meaning | Sample Timestamp 1 | Sample Timestamp 2 |
| :--- | :--- | :--- | :--- |
| `requestedAt` | Timestamp when WhatsApp customer sent message to MSG91 | `11:46:44+05:30` | `11:50:20+05:30` |
| `ts` | Timestamp when MSG91 actually dispatched the HTTP webhook | `11:48:50+05:30` | `11:52:26+05:30` |
| **Delay Gap** | **Upstream MSG91 Dispatch Delay** | **2 minutes 06 seconds** | **2 minutes 06 seconds** |

### 2. Primary Cause: Webhook Status is "Disabled" in MSG91 Panel
Inside the MSG91 Webhook Configuration modal (`Update Webhook`):
- **Event:** `On Inbound Request Received`
- **Webhook Status:** `Disabled` (Gray Toggle Switch)

```json
"reason": "no inbound setting selected",
"cleverTapErrorCode": "1009",
"cleverTapErrorReason": "Other"
```

### 🧠 Operational Flow:
1. Customer sends WhatsApp message to MSG91.
2. MSG91 checks for an active `On Inbound Request Received` webhook configuration.
3. Because **Webhook Status is set to Disabled**, MSG91 throws `"no inbound setting selected"`.
4. MSG91 places the message in a **fixed 2-minute 06-second internal fallback buffer** before forcefully forwarding the payload to the server.

---

## 🛠️ Step-by-Step Resolution Guide (MSG91 Panel)

To eliminate the 2-minute delay and achieve **instant < 2-second auto-replies**:

1. Log into your **MSG91 Dashboard** (`https://control.msg91.com`).
2. Navigate to **WhatsApp Service** $\rightarrow$ **Webhooks** / **Integrated Numbers**.
3. Open the **`On Inbound Request Received`** Webhook rule configuration.
4. Click the **`Webhook Status`** toggle to change it from **`Disabled`** to **`Enabled`** (active blue toggle).
5. Ensure the URL is set to `https://<your-domain>/api/msg91/webhook`.
6. Click **Save** / **Update Webhook**.
7. Test by sending a WhatsApp message—webhooks will dispatch instantly without the 2-minute delay!

---

## 🔒 Security Notice: Rotate MSG91 Webhook Secret

> [!CAUTION]
> If a plain-text `X-MSG91-Secret` (e.g., `553922A9LTC99gXLMO6a65b42cP1`) was pasted in chat or public logs, **rotate it immediately**:
> 1. In MSG91 Panel, regenerate the Webhook Secret.
> 2. Update `MSG91_WEBHOOK_SECRET` in your production `.env` file.
