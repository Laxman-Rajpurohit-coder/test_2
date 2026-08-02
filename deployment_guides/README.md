# 📁 Deployment Guides Directory

Welcome to the **MSG91 WhatsApp Dashboard** deployment guides folder.

Depending on the server environment you choose to deploy your application on, choose the appropriate step-by-step guide below:

---

## 📄 Available Guides

1. **[VPS_DEPLOYMENT_GUIDE.md](file:///C:/daily%20works/day_7/msg91_dashborad/msg91-app/deployment_guides/VPS_DEPLOYMENT_GUIDE.md)**
   * **Target:** Linux VPS (Ubuntu 22.04/24.04 LTS, Debian, DigitalOcean, AWS EC2, Hetzner, Vultr).
   * **Includes:** Full Nginx, Let's Encrypt SSL Certbot, Supervisor process manager (`queue:work` & `reverb:start`), PostgreSQL, and FFmpeg configuration.

2. **[CPANEL_DEPLOYMENT_GUIDE.md](file:///C:/daily%20works/day_7/msg91_dashborad/msg91-app/deployment_guides/CPANEL_DEPLOYMENT_GUIDE.md)**
   * **Target:** cPanel Shared / Managed VPS Hosting.
   * **Includes:** cPanel File Manager root folder setup, MySQL/PostgreSQL setup, cPanel Cron Job for Queue Worker, Node.js App Manager for WebSockets, and FFmpeg path binding.
