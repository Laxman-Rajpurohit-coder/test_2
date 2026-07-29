# 🚀 Production Deployment Guide: MSG91 WhatsApp Dashboard

This document provides the canonical, step-by-step terminal guide for deploying the **MSG91 WhatsApp Dashboard Application** using the **Docker Compose Production Stack** (running PHP 8.5, PostgreSQL 18, Redis, Reverb WebSockets, and Queue Workers).

Using Docker Compose guarantees zero environment drift between development and production environments.

---

## 📋 Deployment Summary Table

| Step | Section Name | Description |
| :--- | :--- | :--- |
| **Step 1** | **Host Dependencies** | Install Docker, Docker Compose, Git, Nginx, UFW, and Certbot. |
| **Step 2** | **App Repository Setup** | Clone repository, configure `docker/php.ini` upload limits, set permissions. |
| **Step 3** | **Production Environment (`.env`)** | Configure `.env` with Reverb keys, DB credentials, and MSG91 secrets. |
| **Step 4** | **Docker Stack Boot & Build** | Start containers (`compose.yaml`), run migrations, and compile Vite assets. |
| **Step 5** | **Nginx SSL & WSS Proxy** | Configure Nginx reverse proxy with Let's Encrypt SSL certificate. |
| **Step 5.5** | **Host Firewall Lockdown** | Block external access to Postgres (5432) and Redis (6379) via UFW. |
| **Step 6** | **MSG91 Webhook Lock** | Register production HTTPS URL and secret header in MSG91 Panel. |

---

## 🛠️ Step-by-Step Production Deployment Commands

### Step 1: Install System Dependencies (Host Server)

Run the following commands on your Linux VPS (Ubuntu 22.04 / 24.04 LTS, Debian, AWS EC2, DigitalOcean, etc.):

```bash
# Update package repositories
sudo apt update && sudo apt upgrade -y

# Install Docker Engine & Docker Compose Plugin
sudo apt install -y ca-certificates curl gnupg git nginx ufw certbot python3-certbot-nginx
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

---

### Step 2: Clone Codebase & Set Permissions

```bash
# Navigate to web root directory
cd /var/www

# Clone repository
sudo git clone https://github.com/your-username/msg91-dashboard.git whatsapp-app
cd /var/www/whatsapp-app

# Set Directory Ownership & Permissions
sudo chown -R www-data:www-data /var/www/whatsapp-app
sudo chmod -R 775 storage bootstrap/cache
```

#### 🐘 PHP Upload Limits Configuration (`docker/php.ini`)
Verify `docker/php.ini` contains:
```ini
[PHP]
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
```
This guarantees voice notes and media uploads operate cleanly without hitting PHP's default 2MB limits.

---

### Step 3: Configure Production `.env`

Create your production `.env` file:

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
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=whatsapp_production_db
DB_USERNAME=whatsapp_prod_user
DB_PASSWORD=your_secure_production_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

MSG91_AUTH_KEY=your_msg91_auth_key
MSG91_INTEGRATED_NUMBER=91XXXXXXXXXX
MSG91_WEBHOOK_SECRET=antigravity_secret_pass_key_2026_ver

# Server-Side Reverb (Internal PHP connection inside Docker network to container 'reverb')
REVERB_APP_ID=466982
REVERB_APP_KEY=emjclc1h11mphtfbm1sl
REVERB_APP_SECRET=c9hyljlrmgp2fvcoalru
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

# Browser-Side Echo (Connects over HTTPS/WSS via Nginx /app proxy)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=whatsapp.yourdomain.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

WWWGROUP=1000
WWWUSER=1000
```

---

### Step 4: Boot Production Docker Stack & Compile Frontend

Start all 5 Docker containers (`laravel.test`, `queue-worker`, `reverb`, `pgsql`, `redis`):

```bash
docker compose up -d
```

Run Artisan setup & compile Vite frontend assets **inside the PHP container** (with baked `.env` variables):

```bash
# Generate App Key & Database Migrations
docker compose exec -T laravel.test php artisan key:generate
docker compose exec -T laravel.test php artisan migrate --force
docker compose exec -T laravel.test php artisan storage:link

# Clear & Cache Configuration
docker compose exec -T laravel.test php artisan config:cache
docker compose exec -T laravel.test php artisan route:cache
docker compose exec -T laravel.test php artisan view:cache

# Compile production Vite frontend bundle WITH baked .env variables
docker compose exec -T laravel.test npm run build
```

---

### Step 5: Nginx SSL & Single-Domain WSS Reverse Proxy

Create an Nginx server block on the host machine:

```bash
sudo nano /etc/nginx/sites-available/whatsapp-dashboard
```

Paste the configuration:

```nginx
server {
    listen 80;
    server_name whatsapp.yourdomain.com;

    client_max_body_size 30M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    # Forward Web HTTP Requests to Laravel Docker Container (Port 80)
    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Single-Domain WSS Proxy: Forward WebSocket Upgrade Requests to Reverb Container (Port 8080)
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }
}
```

Enable site & Obtain Let's Encrypt SSL Certificate:

```bash
sudo ln -s /etc/nginx/sites-available/whatsapp-dashboard /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Install Free SSL Certificate via Let's Encrypt
sudo certbot --nginx -d whatsapp.yourdomain.com
```

---

### Step 5.5: Lock Down Host Firewall (Postgres & Redis)

`compose.yaml` publishes Postgres (5432) and Redis (6379) on `0.0.0.0` by default—required for local development, but unsafe on a public VPS unless restricted. This step blocks external access to those ports while leaving Nginx (80/443) and SSH open.

```bash
# Allow SSH and Web HTTP/HTTPS Traffic
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Deny external access to Postgres and Redis explicitly
sudo ufw deny 5432/tcp
sudo ufw deny 6379/tcp

# Enable Firewall
sudo ufw enable
sudo ufw status verbose
```

**Verify from an external machine (not the VPS itself):**
```bash
nc -zv -w3 whatsapp.yourdomain.com 5432
nc -zv -w3 whatsapp.yourdomain.com 6379
# Both should time out / refuse — if either succeeds, STOP and recheck UFW / Cloud Security Groups.
```

---

### Step 6: Lock MSG91 Webhook Endpoint

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

### 🌐 Optional: Cloudflare Tunnel Deployment (`cloudflared`)

If exposing the Docker Compose stack via Cloudflare Tunnel instead of Nginx Certbot:

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

- [ ] **Docker Containers:** Run `docker compose ps` to verify all 5 services (`laravel.test`, `queue-worker`, `reverb`, `pgsql`, `redis`) are UP.
- [ ] **FFmpeg Transcoding:** Verify voice recordings produce mono 16kHz Opus `.ogg` files in `/var/www/whatsapp-app/storage/app/public/media/`.
- [ ] **Storage Link:** Verify `/var/www/whatsapp-app/public/storage` symlink exists.
- [ ] **Host Firewall:** Verify UFW blocks ports 5432 and 6379 externally.
- [ ] **SSL Encryption:** Test `https://whatsapp.yourdomain.com/api/msg91/webhook` with `200 OK` response.
