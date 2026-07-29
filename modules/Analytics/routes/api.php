<?php

use App\Http\Middleware\IdentifyTenant;
use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::middleware(['auth', IdentifyTenant::class])->prefix('api/analytics')->group(function () {
    Route::get('/overview', [AnalyticsController::class, 'overview'])->name('api.analytics.overview');
});
