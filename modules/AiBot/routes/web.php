<?php

use Illuminate\Support\Facades\Route;
Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // AI Settings are now managed in TenantSettingsController
});
