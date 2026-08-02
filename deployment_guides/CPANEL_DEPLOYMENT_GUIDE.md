# 📌 cPanel Hosting Deployment Guide: MSG91 WhatsApp Dashboard

This guide explains how to deploy the **MSG91 WhatsApp Dashboard** on a standard **cPanel Shared/VPS Hosting** environment.

---

## 🛠️ cPanel Deployment Steps

### Step 1: Upload Code & Set Web Directory

1. Compress your project folder into a `.zip` file (excluding `node_modules` and `vendor`).
2. Open **cPanel File Manager**.
3. Upload the `.zip` file to your root home directory (e.g., `/home/username/msg91-app`) **OUTSIDE** the public web root (`public_html`).
4. Extract the `.zip` contents inside `/home/username/msg91-app`.
5. If using a subdomain (e.g., `whatsapp.yourdomain.com`), go to **cPanel Domains / Subdomains** and set the Document Root to:
   ```text
   /home/username/msg91-app/public
   ```
6. If deploying on your main domain, copy the contents of `/home/username/msg91-app/public` into `public_html` and edit `index.php` to point to:
   ```php
   require __DIR__.'/../msg91-app/vendor/autoload.php';
   $app = require_once __DIR__.'/../msg91-app/bootstrap/app.php';
   ```

---

### Step 2: Set Up PostgreSQL / MySQL Database in cPanel

1. Open **cPanel Databases** (PostgreSQL Database Wizard or MySQL Database Wizard).
2. Create database `username_whatsapp_db`.
3. Create user `username_dbuser` with a secure password.
4. Assign all privileges to `username_dbuser` on `username_whatsapp_db`.

---

### Step 3: Configure Environment (`.env`) via Terminal / File Manager

Create or edit `/home/username/msg91-app/.env`:

```ini
APP_NAME="MSG91 Dashboard"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://whatsapp.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=username_whatsapp_db
DB_USERNAME=username_dbuser
DB_PASSWORD=your_secure_password

CACHE_STORE=redis
QUEUE_CONNECTION=database
SESSION_DRIVER=redis

MSG91_AUTH_KEY=your_msg91_auth_key
MSG91_INTEGRATED_NUMBER=91XXXXXXXXXX
MSG91_WEBHOOK_SECRET=antigravity_secret_pass_key_2026_ver
```

---

### Step 4: Run Migrations & Storage Link in cPanel Terminal

Open **cPanel Terminal** (or SSH):

```bash
cd /home/username/msg91-app

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Run Migrations & Storage Symlink
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

---

### Step 5: Setup Background Queue Worker in cPanel Cron Jobs

Since cPanel does not always allow Supervisor root access, set up a **cPanel Cron Job** to process background jobs automatically:

1. Open **cPanel $\rightarrow$ Cron Jobs**.
2. Set Common Settings to **Every Minute (`* * * * *`)**.
3. Command:
   ```bash
   cd /home/username/msg91-app && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
   ```

---

### Step 6: FFmpeg Path Verification in cPanel

Most cPanel hosts have FFmpeg installed at `/usr/bin/ffmpeg` or `/usr/local/bin/ffmpeg`.
Verify in cPanel Terminal:
```bash
which ffmpeg
```
If your host provides a custom path (e.g. `/home/username/bin/ffmpeg`), update `ChatController.php` command path accordingly.

---

### Step 7: WebSockets (Laravel Reverb) on cPanel

1. Go to **cPanel $\rightarrow$ Setup Node.js App** (if available) to run Reverb.
2. Alternatively, configure **Pusher** or **Soketi** credentials in `.env` if cPanel restricts persistent port listening (`8080`).

---

### Step 8: Update MSG91 Webhook Endpoint

In your MSG91 Control Panel:
* Webhook URL: `https://whatsapp.yourdomain.com/api/msg91/webhook`
* Header: `X-MSG91-Secret: antigravity_secret_pass_key_2026_ver`
