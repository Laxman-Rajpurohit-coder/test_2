<?php

use Illuminate\Support\Facades\Route;
Route::middleware(['web', 'auth:web,admin', 'verified'])->group(function () {
    // AI Settings are now managed in TenantSettingsController
});
