# Project Operational Guidelines & Learned Rules

## 1. User Directives & Change Enforcement
- **Inspection Mode**: When the user asks "just asking dont do changes" or requests diagnostic explanations, inspect files and explain findings ONLY. Do not make code edits or run git commands.
- **Push & Deployment Guard**: Never stage, commit, or push code to Railway/GitHub unless explicitly directed by the user ("run it", "push code"). Always build (`npm run build`) and test locally first.

## 2. Database & PostgreSQL Strict Type Safety
- **UUID vs. Phone Number Queries**: Primary keys on `contacts` and `messages` tables are UUIDs. Before querying `$id` against the primary key, check `Str::isUuid($id)`. If `$id` is a phone number string, query ONLY string columns (e.g. `phone_number`, `customer_number`) to prevent PostgreSQL `SQLSTATE[22P02]` UUID type cast crashes (`invalid input syntax for type uuid`).
- **PostgreSQL JSON Pattern Matching**: PostgreSQL formats JSON text with spaces around colons (`"type": "image"` vs `"type":"image"`). Substring pattern queries for content types must include flexible wildcard patterns (e.g., `LIKE '%"type"%"image"%'`).

## 3. Multi-Tenant Isolation & Super Admin Scopes
- **Super Admin Global Scope Bypass**: Super Admin users authenticated under `auth('admin')` do not belong to a single tenant. When building Super Admin aggregation views (e.g. `/admin/tenants`), use `Message::withoutGlobalScopes()` to prevent `TenantResolverService` security aborts.

## 4. Meta WhatsApp 24-Hour Session Rules
- **Inbound-Only Session Reset**: Free-form text and custom image uploads are restricted to 24 hours from the customer's last **inbound** message. Outbound template messages reach the recipient but do NOT open a free-form session until the customer replies back inbound.
