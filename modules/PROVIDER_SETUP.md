# ⚙️ Service Provider Registration Standard

Each new module registers its routes and dependencies via its own `ServiceProvider`.

To register a module's provider, add it to `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    Modules\Analytics\AnalyticsServiceProvider::class,
];
```
