# Fix: Enable contacts_bulk_messaging Feature for Owner Tenant

## Root Cause

The `/contacts` route is gated behind the `contacts_bulk_messaging` feature flag:

```php
Route::middleware(['feature:contacts_bulk_messaging'])->group(function () {
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    // ... other routes
});
```

However, the owner tenant (`Snazzy IT Solutions`) does **not have this feature enabled** in its `features` column.

The `CheckTenantFeature` middleware (which fails closed by design) rejects the request with 403:

```php
if (!$tenant || !$tenant->hasFeature($featureKey)) {
    abort(403, "This feature (contacts_bulk_messaging) is not enabled for your account.");
}
```

## Why This Wasn't Caught Earlier

- `/logs` has **no feature gate** and works fine
- `/templates` has a **feature gate in its module routes**, but only tested via Tinker, not in a real browser session
- The `RoleMiddleware` fix for `/templates` worked, but `/contacts` has a **separate, independent issue: the feature flag**

## Solution

Enable the `contacts_bulk_messaging` feature for the owner tenant. This can be done via:

### Option 1: Admin Panel (Recommended)

1. Log in as admin
2. Go to `/admin/tenants`
3. Click on "Snazzy IT Solutions" tenant
4. Check `contacts_bulk_messaging` in the features section
5. Save

### Option 2: Tinker (Immediate Fix)

```php
$tenant = Tenant::where('name', 'Snazzy IT Solutions')->first();
$tenant->update([
    'features' => [
        'contacts_bulk_messaging' => true,
        // preserve any other features...
    ]
]);
```

### Option 3: Direct Database

```sql
UPDATE tenants 
SET features = JSON_SET(features, '$.contacts_bulk_messaging', true)
WHERE name = 'Snazzy IT Solutions';
```

## Verification

After enabling the feature, the owner should be able to access:
- `/contacts` (Contacts & Campaigns)
- `/campaigns` and related routes

All routes in that group should now be accessible with a 200 response.

## Why This Feature Gate Exists

The `contacts_bulk_messaging` feature gate is likely a **licensing or beta feature** that is selectively enabled for tenants. Test tenants in the codebase explicitly set it to `true` (see `tests/Feature/ContactTenantIsolationTest.php`, `tests/Feature/CampaignControllerTest.php`, etc.).

The production owner tenant was apparently created **before this feature gate was added** or it was not initialized with this feature enabled. Enabling it now will make the routes accessible.

