# Deployment Handoff Guide — MSG91 WhatsApp SaaS

**Purpose:** This document exists because deploying this app to Railway surfaced a
long list of environment-specific gotchas that had nothing to do with the
application code — they were purely "this platform doesn't know what our local
Docker setup already knows." This guide exists so the *next* deployment (to
Railway, a VPS, Render, Fly.io, or anywhere else) doesn't rediscover the same
problems from scratch.

**Core principle:** This app was built and tested against a specific, correct
local environment (Docker Compose + Laravel Sail). Every hosting platform is a
*different* environment that has to be told, explicitly, how to reproduce that
setup. Nothing is inherited automatically. Read this whole document before
touching a new server.

---

## 1. Architecture — 5 required running processes, always

Regardless of hosting platform, this app needs **five separate long-running
things**. This is not optional and does not change based on where you deploy:

| # | Process | Command | Public access needed? |
|---|---|---|---|
| 1 | Web server | Framework default (serves HTTP/Inertia) | Yes — the only public one |
| 2 | Queue worker | `php artisan queue:work --tries=3 --backoff=5,15,30` | No |
| 3 | Reverb (WebSockets) | `php artisan reverb:start --host=0.0.0.0 --port=8080` | Only if frontend connects to it directly |
| 4 | PostgreSQL | Managed service or container | No — internal only |
| 5 | Redis | Managed service or container | No — internal only |

**If any deployment only stands up #1**, the app will *look* like it's working
(pages load, login works) but:
- No queued job will ever run (bot replies, outbound MSG91 sends, flow
  execution — all silently stuck forever)
- No real-time chat updates (WebSocket has nothing to connect to)

This exact mistake was made on the first Railway deploy — one service was
created, and #2/#3 were simply missing. Always verify all five exist before
declaring a deployment "done."

---

## 2. The two deployment models — pick one, don't mix

### Model A — Bring your own container (recommended, most portable)

Use the `compose.yaml` / Sail-based Docker setup already in this repo,
verified extensively in local testing. Deploy it as-is on:
- A plain VPS (any provider) — see `DEPLOYMENT_GUIDE.md` in this repo
- Any platform that supports "deploy from Dockerfile" instead of
  auto-detection (Railway supports this as an alternative to Nixpacks; so do
  Render, Fly.io, and others)

**Why this is more portable:** the image already has the correct PHP
extensions (`phpredis` compiled in), the correct nginx/PHP-FPM config, and the
correct file structure baked in. Moving this same image to a different host
mostly changes *where* it runs, not *how* it's configured.

### Model B — Platform auto-detection (e.g. Railway's default Nixpacks build)

The platform inspects your code and *guesses* how to build/run it — no
Dockerfile involved. This is faster to get started with, but every gotcha in
Section 4 below came from this model specifically. Auto-detection can silently
change behavior between builds (cache differences, platform tooling updates)
even without you changing anything.

**Recommendation:** use Model A for anything beyond a quick demo. If using
Model B, budget real time for the checklist in Section 4 on every new
environment.

---

## 3. Environment variables — the complete list, every deployment needs these

Nothing is inherited from your local `.env`. Every one of these must be set
explicitly on every new service in every new environment.

```
APP_NAME=...
APP_ENV=production
APP_KEY=base64:...          # generate via: php artisan key:generate --show
APP_DEBUG=false             # NEVER true in production — leaks stack traces
APP_URL=https://your-real-domain

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

REDIS_CLIENT=predis         # see Section 4.1 — do not assume phpredis is available
REDIS_HOST=...
REDIS_PORT=6379
REDIS_PASSWORD=...

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database     # or redis, but be consistent

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=...
REVERB_PORT=8080
REVERB_SCHEME=https
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=your-real-domain
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

MSG91_AUTH_KEY=...
MSG91_INTEGRATED_NUMBER=...
MSG91_WEBHOOK_SECRET=...    # MUST match exactly what's saved in MSG91's own
                            # dashboard for this webhook — see Section 4.4
```

**Every one of the three app-code services (web, worker, Reverb) needs the
DB/Redis/queue variables identically.** Each service has its own isolated
variable set on most platforms — nothing is shared automatically unless you
explicitly configure shared/reference variables.

---

## 4. Known gotchas — checklist for any new environment

Go through every item below on any new deployment target. Each one caused a
real outage or bug this session.

### 4.1 — Redis client: `phpredis` vs `predis`
Local Sail images ship the native `phpredis` PHP extension pre-compiled.
Most generic build platforms do **not** install it by default. Fix:
- `composer require predis/predis`
- Set `REDIS_CLIENT=predis` on every service touching Redis
- **Do not** hand-edit `composer.json` without running
  `composer update <package>` afterward — the lock file must match, or the
  build fails outright with a lock-file-mismatch error.

### 4.2 — nginx / web server auto-detection can silently misconfigure
On platforms that auto-generate the web-server config (e.g. Nixpacks),
confirm after every deploy that:
- The document root points at `public/`, not the project root (Laravel's
  actual entry point is `public/index.php`)
- A catch-all rewrite to `index.php` exists for pretty URLs (without it,
  only `/` works — every other route 404s at the web-server layer before
  Laravel even runs)

Auto-detection for both of these can work on one build and silently fail on
the next (cache state, platform tooling changes) even with no code changes.
**Prefer setting these explicitly** rather than trusting auto-detection long
term.

### 4.3 — Environment variables start empty on every new service
Adding a new service (e.g. the queue worker) does not copy variables from an
existing one. Check this explicitly every time a new service is created —
this caused the queue worker to try connecting to SQLite (a bare Laravel
default) instead of the real Postgres/Redis, simply because nothing had been
set yet.

### 4.4 — MSG91 webhook secret must match *exactly*, on both sides, always
`MSG91_WEBHOOK_SECRET` (your server) and the secret shown in MSG91's own
webhook configuration dashboard are **two independently editable values** —
nothing keeps them in sync automatically. A mismatch causes every inbound
webhook to fail with `401 Unauthorized`, with MSG91 showing the message was
received on their side while your server never logs a matching request.
**Whenever the MSG91 webhook secret is regenerated on either side, update the
other immediately, and redeploy.**

### 4.5 — MSG91 "Webhook Status" toggle, per number
Separately from the secret, each integrated WhatsApp number has its own
webhook enable/disable toggle in MSG91's dashboard, scoped to the
"On Inbound Request Received" event. If disabled (or never explicitly
enabled for a newly added number), MSG91 will log the message on its own
side but **never call your webhook at all** — no 401, no error, just
silence, until this is manually enabled per number. **Add this to onboarding
checklist for every new tenant/number.**

### 4.6 — Multiple `location /` blocks can silently break the whole web server
If a config-generation template has two independent conditionals that can
each render a fallback rule, and both evaluate true on the same build, you
get a duplicate directive and the web server fails to start entirely. If you
ever set an explicit override for something the platform normally
auto-detects, also explicitly disable the auto-detected path, don't leave
both live.

### 4.7 — Seeder/demo-data safety
Any database seeder that creates login accounts with hardcoded or predictable
passwords must be guarded from running in production
(`if (app()->isProduction()) return;`), or use randomized passwords printed
once to the console. Confirm no deploy script runs `db:seed --force`
unconditionally.

---

## 5. Per-deployment checklist — copy this for every new environment

- [ ] All 5 processes (web, worker, Reverb, Postgres, Redis) exist and are
      running
- [ ] `APP_KEY` set to a real generated value (not blank, not a placeholder)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] DB and Redis variables set identically on **every** app-code service
- [ ] `REDIS_CLIENT=predis` set, `predis/predis` present in
      `composer.lock` (not just `composer.json`)
- [ ] Web server document root confirmed pointing at `public/`
- [ ] A test request to a non-root route (e.g. `/chat`) succeeds — confirms
      the catch-all rewrite is active, not just `/`
- [ ] `MSG91_WEBHOOK_SECRET` confirmed character-for-character identical to
      MSG91's dashboard value for this specific webhook
- [ ] MSG91 "Webhook Status" toggle confirmed enabled for the specific
      integrated number in use
- [ ] Send one real test WhatsApp message end-to-end — confirm it appears
      in the live inbox
- [ ] Send one real outbound reply — confirm it reaches the real phone
- [ ] Confirm the seeder cannot run with default/weak credentials in this
      environment
- [ ] Full `php artisan test` suite run against this environment's actual
      database engine (not SQLite, if production uses Postgres) — SQLite
      can silently mask real type/constraint bugs that only Postgres
      enforces

---

## 6. If something breaks that isn't in this list

Diagnose in this order, cheapest checks first:
1. Is the relevant service actually running? (all 5 processes)
2. Are this specific service's environment variables actually set, and
   correct? (check the exact value, don't assume "it's set" means "it's
   right" — values can be stale, mistyped, or copied from the wrong source)
3. Check the platform's own logs for the actual exception, not just a
   generic error page
4. If it's webhook-related, check the *provider's* (MSG91) own delivery
   logs, not just your server — this tells you whether the request even
   arrived
5. Once the actual error is known, it's almost always one of: a missing
   env var, a mismatched credential between two systems, or a PHP
   extension the new environment doesn't have that the old one did

Document any new gotcha found here, in this file, so the next deployment
doesn't rediscover it.
