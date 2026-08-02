# Local Development & Bootstrapping Guide

Complete, step-by-step setup instructions to clone, configure, and execute the MSG91 WhatsApp Live Hub from zero.

---

## 📋 Prerequisites
Ensure your local development machine has the following tools installed:
* [Docker Desktop](https://www.docker.com/products/docker-desktop/) (running with WSL2 or Hyper-V)
* [Git](https://git-scm.com/)
* [PHP 8.2+](https://www.php.net/) & [Composer](https://getcomposer.org/)
* [Node.js 18+](https://nodejs.org/) & `npm`
* [Ngrok CLI](https://ngrok.com/download)

---

## 🚀 Step-by-Step Setup Instructions

### 1. Clone & Navigate
```bash
git clone https://github.com/your-username/msg91_dashborad.git
cd msg91_dashborad/msg91-app
```

### 2. Environment Configuration
Copy the `.env.example` file to `.env`:
```bash
cp .env.example .env
```

Open `.env` and verify the core service configurations:
```env
APP_NAME="MSG91 WhatsApp Live Hub"
APP_URL=http://localhost

# Database Configuration (PostgreSQL in Docker)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379

# WebSockets (Laravel Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=466982
REVERB_APP_KEY=emjclc1h11mphtfbm1sl
REVERB_APP_SECRET=c9hyljlrmgp2fvcoalru
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST="0.0.0.0"

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# MSG91 WhatsApp API Credentials
MSG91_AUTH_KEY="YOUR_MSG91_AUTH_KEY"
MSG91_INTEGRATED_NUMBER="91XXXXXXXXXX"
MSG91_WEBHOOK_SECRET="YOUR_WEBHOOK_SECRET_KEY"
```

### 3. Start Docker Containers (Laravel Sail)
Boot up the PostgreSQL, Redis, and PHP 8.5 containers:
```bash
./vendor/bin/sail up -d
```
*(On Windows PowerShell, use `docker compose up -d`)*

### 4. Database Migrations
Run all database schema migrations:
```bash
docker compose exec -T laravel.test php artisan migrate --force
```

### 5. Build Frontend Assets
Compile Vite production bundles:
```bash
docker compose exec -T laravel.test npm run build
```

### 6. Start Background Workers
In separate terminal windows, start the WebSocket server and Queue Worker:
```bash
# Terminal A: Start Reverb WebSocket Server
docker compose exec laravel.test php artisan reverb:start

# Terminal B: Start Background Queue Worker
docker compose exec laravel.test php artisan queue:work
```

### 7. Expose Ngrok Webhook Tunnel
In a new terminal window, run Ngrok to expose local port 80:
```bash
ngrok http 80
```

Copy the generated HTTPS Forwarding URL (e.g. `https://xxxx.ngrok-free.app`).

### 8. MSG91 Webhook Setup
Go to your **MSG91 Dashboard -> WhatsApp -> Webhooks**:
* **URL:** `https://xxxx.ngrok-free.app/msg91/webhook`
* **HTTP Method:** `POST`
* **Custom Headers:** `X-MSG91-Secret: YOUR_WEBHOOK_SECRET_KEY`

---

## 🌐 Accessing the Live Dashboard
Open your browser and navigate to:
`http://localhost/chat`

**Default Admin Account:**
* **Email:** `test@example.com`
* **Password:** `password`
