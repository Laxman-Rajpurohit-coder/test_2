# 🏗️ Modular Architecture Blueprint

This directory houses self-contained, domain-driven application modules for the MSG91 Dashboard ecosystem.

---

## 📁 Directory Structure

```text
modules/
├── README.md                  ← Modular Architecture Guide
├── COMPOSER_SETUP.md          ← One-time PSR-4 Namespace Configuration
├── PROVIDER_SETUP.md          ← Service Provider Registration Standard
├── FRONTEND_SETUP.md          ← Inertia Page Resolver Standard
└── Analytics/                 ← Analytics Domain Module
    ├── src/
    │   ├── Http/
    │   │   └── Controllers/
    │   │       └── AnalyticsController.php
    │   ├── Services/
    │   │   └── AnalyticsService.php
    │   └── AnalyticsServiceProvider.php
    └── routes/
        ├── web.php
        └── api.php
```

---

## 🎯 Modular Design Rules

1. **Self-Contained Routes & Logic:** Each module registers its own web and API routes via its own `ServiceProvider`.
2. **Thin Controllers & Service Layer:** Controllers handle HTTP validation and response formatting. Business logic and database queries reside inside `Services/`.
3. **Domain Frontends:** Inertia React pages for a module reside in `resources/js/Modules/<ModuleName>/Pages/`.
