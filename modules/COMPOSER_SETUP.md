# 📦 One-Time Composer PSR-4 Configuration

To enable auto-loading for all modules under `modules/`, add the `"Modules\\": "modules/"` mapping to `composer.json`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\": "modules/",
        "Database\\Factories\\": "database/factories/",
        "Database\\Seeders\\": "database/seeders/"
    }
}
```

After modifying `composer.json`, run:

```bash
composer dump-autoload
```
