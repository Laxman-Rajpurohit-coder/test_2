<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
});
