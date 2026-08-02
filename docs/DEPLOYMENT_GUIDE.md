# 🚀 Production Deployment Guide: MSG91 WhatsApp Dashboard

This document provides a clean, step-by-step terminal guide for deploying the **MSG91 WhatsApp Dashboard Application** to any production Linux VPS (Ubuntu 22.04 / 24.04 LTS, Debian, AWS EC2, DigitalOcean, Hetzner, etc.).

---

## 📋 Deployment Summary Table

| Step | Section Name | Description |
| :--- | :--- | :--- |
| **Step 1** | **System Dependencies** | Install PHP 8.2, PostgreSQL 15, Redis 7, Nginx, Supervisor, and FFmpeg. |
| **Step 2** | **PostgreSQL Setup** | Create `whatsapp_db` and `whatsapp_user` database credentials. |
| **Step 3** | **App Deployment** | Clone repository, run `composer install` & `npm run build`. |
| **Step 4** | **Environment & PHP Config** | Set `.env` variables, run migrations, and set PHP upload limits. |
| **Step 5** | **Supervisor Daemons** | Keep `queue:work` and `reverb:start` running 24/7. |
| **Step 6** | **Nginx & SSL Certbot** | Configure WebSockets proxy and obtain free Let's Encrypt SSL certificate. |
| **Step 7** | **MSG91 Webhook Lock** | Register production HTTPS URL and secret header in MSG91 Panel. |

---

## 🛠️ Step-by-Step Deployment Commands

### Step 1: Install System Dependencies

Run the following commands on your Linux VPS:

```bash
# Update package repositories
sudo apt update && sudo apt upgrade -y

# Install Core Tools & Build Utilities
sudo apt install -y curl git unzip supervisor nginx ffmpeg redis-server postgresql postgresql-contrib

# Install PHP 8.2 & Required Extensions
sudo apt install -y php8.2-fpm php8.2-cli php8.2-pgsql php8.2-redis php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip

# Install Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

### Step 2: Create PostgreSQL Database & User

Log into PostgreSQL and create the application database and user:

```bash
sudo -u postgres psql
```

Execute the SQL commands inside the `psql` shell:

```sql
CREATE DATABASE whatsapp_db;
CREATE USER whatsapp_user WITH PASSWORD 'your_secure_db_password';
GRANT ALL PRIVILEGES ON DATABASE whatsapp_db TO whatsapp_user;
ALTER DATABASE whatsapp_db OWNER TO whatsapp_user;
\q
```

---

### Step 3: Clone Codebase & Install Dependencies

```bash
# Navigate to web root directory
cd /var/www

# Clone repository
sudo git clone https://github.com/your-username/msg91-dashboard.git whatsapp-app
cd /var/www/whatsapp-app

# Set Directory Ownership & Permissions
sudo chown -R www-data:www-data /var/www/whatsapp-app
sudo chmod -R 775 storage bootstrap/cache

# Install PHP Dependencies via Composer
composer install --no-dev --optimize-autoloader

# Install Node Dependencies & Build Production Frontend Assets
npm ci
npm run build
```

---

### Step 4: Production Environment (`.env`) & PHP Config

Create and configure your production `.env` file:

```bash
cp .env.example .env
nano .env
```

Set the following production environment variables:

```ini
APP_NAME="MSG91 Dashboard"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://whatsapp.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=whatsapp_db
DB_USERNAME=whatsapp_user
DB_PASSWORD=your_secure_db_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MSG91_AUTH_KEY=your_msg91_auth_key
MSG91_INTEGRATED_NUMBER=91XXXXXXXXXX
MSG91_WEBHOOK_SECRET=antigravity_secret_pass_key_2026_ver
```

Generate App Key, Link Storage, and Run Database Migrations:

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Configure PHP Upload Limits for Media (`php.ini`):
Edit `/etc/php/8.2/fpm/php.ini` and `/etc/php/8.2/cli/php.ini`:
```ini
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
```
Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

---

### Step 5: Configure Supervisor Process Daemon (`queue:work` & `reverb`)

Create a Supervisor configuration file to keep background Queue Workers and WebSocket servers running 24/7 across server reboots:

```bash
sudo nano /etc/supervisor/conf.d/whatsapp-workers.conf
```

Paste the following configuration:

```ini
[program:whatsapp-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/whatsapp-app/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/whatsapp-app/storage/logs/queue-worker.log
stopwaitsecs=3600

[program:whatsapp-reverb]
command=php /var/www/whatsapp-app/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/whatsapp-app/storage/logs/reverb.log
```

Start Supervisor Services:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
sudo supervisorctl status
```

---

### Step 6: Nginx Web Server & SSL Setup (Certbot)

Create an Nginx server block:

```bash
sudo nano /etc/nginx/sites-available/whatsapp-dashboard
```

Paste the configuration:

```nginx
server {
    listen 80;
    server_name whatsapp.yourdomain.com;
    root /var/www/whatsapp-app/public;

    client_max_body_size 30M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Proxy WebSocket Traffic to Laravel Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site & SSL Certificate:

```bash
sudo ln -s /etc/nginx/sites-available/whatsapp-dashboard /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Install SSL Certificate via Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d whatsapp.yourdomain.com
```

---

### Step 7: Lock MSG91 Webhook Endpoint

1. Log into **[MSG91 Control Panel](https://control.msg91.com/)**.
2. Go to **WhatsApp** $\rightarrow$ **Webhook (New)**.
3. Update Webhook URL:
   ```text
   https://whatsapp.yourdomain.com/api/msg91/webhook
   ```
4. Set Headers:
   * `Content-Type`: `application/json`
   * `X-MSG91-Secret`: `antigravity_secret_pass_key_2026_ver`
5. Select Events:
   * ✅ **On Inbound Request Received**
   * ✅ **On Inbound Report Received**
6. Click **Save**.

---

### 🌐 Alternative Setup: Cloudflare Tunnel (`cloudflared`)

If deploying locally without a public IP:

```bash
# Install Cloudflare Tunnel CLI
sudo mkdir -p --mode=0755 /etc/apt/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /etc/apt/keyrings/cloudflare-main.gpg >/dev/null
echo "deb [signed-by=/etc/apt/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared jammy main" | sudo tee /etc/apt/sources.list.d/cloudflared.list
sudo apt update && sudo apt install -y cloudflared

# Authenticate Cloudflare & Create Tunnel
cloudflared tunnel login
cloudflared tunnel create whatsapp-dashboard
cloudflared tunnel route dns whatsapp-dashboard whatsapp.yourdomain.com

# Run Tunnel as System Daemon
sudo cloudflared service install <TUNNEL_TOKEN>
```

---

## 🔒 Verification & Maintenance Checklist

- [ ] **Queue Workers:** Run `sudo supervisorctl status` to verify `whatsapp-queue-worker` is active.
- [ ] **FFmpeg Transcoding:** Verify voice recordings produce mono 16kHz Opus `.ogg` files in `/var/www/whatsapp-app/storage/app/public/media/`.
- [ ] **Storage Link:** Verify `/var/www/whatsapp-app/public/storage` symlink exists.
- [ ] **SSL Encryption:** Test `https://whatsapp.yourdomain.com/api/msg91/webhook` with `200 OK` response.
