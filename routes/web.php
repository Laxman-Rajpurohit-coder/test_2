<?php

use Modules\Analytics\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BotTriggerController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Master SAAS Dashboard Route (Points to Modules\Analytics\Http\Controllers\AnalyticsController)
Route::get('/dashboard', [\Modules\Analytics\Http\Controllers\AnalyticsController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

// Admin Auth Routes
Route::get('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'store'])->name('admin.login.store');
Route::post('/admin/logout', [\App\Http\Controllers\Admin\AuthController::class, 'destroy'])->name('admin.logout');

Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tenants', [\App\Http\Controllers\Admin\TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [\App\Http\Controllers\Admin\TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}/stats', [\App\Http\Controllers\Admin\TenantController::class, 'stats'])->name('tenants.stats');
    Route::patch('/tenants/{tenant}/status', [\App\Http\Controllers\Admin\TenantController::class, 'updateStatus'])->name('tenants.status');
    Route::delete('/tenants/{tenant}', [\App\Http\Controllers\Admin\TenantController::class, 'destroy'])->name('tenants.destroy');

    Route::post('/impersonate/{tenant}', [\App\Http\Controllers\Admin\ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::post('/impersonate-stop', [\App\Http\Controllers\Admin\ImpersonationController::class, 'stop'])->name('impersonate.stop');
});

Route::middleware(['auth:web,admin', \App\Http\Middleware\BlockImpersonationWrites::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Coming Soon Route
    Route::get('/coming-soon', function () {
        return Inertia::render('ComingSoon');
    })->name('coming-soon');

    // Chat Application Routes (Inbox)
    Route::get('/chat', [ChatController::class, 'view'])->name('chat');
    Route::get('/api/conversations', [ChatController::class, 'index']);
    Route::get('/api/conversations/{id}/messages', [ChatController::class, 'show']);
    Route::post('/api/conversations/{id}/messages', [ChatController::class, 'store']);
    Route::post('/api/conversations/{id}/media', [ChatController::class, 'storeMedia']);

    // Automated Bot Trigger Routes
    Route::get('/bot-triggers', [BotTriggerController::class, 'index'])->name('bot-triggers.index');
    Route::post('/bot-triggers', [BotTriggerController::class, 'store'])->name('bot-triggers.store');
    Route::put('/bot-triggers/{id}', [BotTriggerController::class, 'update'])->name('bot-triggers.update');
    Route::patch('/bot-triggers/{id}/toggle', [BotTriggerController::class, 'toggleActive'])->name('bot-triggers.toggle');
    Route::delete('/bot-triggers/{id}', [BotTriggerController::class, 'destroy'])->name('bot-triggers.destroy');

    // Tenant Integration Settings Routes
    Route::get('/settings/tenant', [TenantSettingsController::class, 'edit'])->name('settings.tenant.edit');
    Route::post('/settings/tenant', [TenantSettingsController::class, 'update'])->name('settings.tenant.update');
    Route::post('/settings/tenant/numbers', [TenantSettingsController::class, 'storeNumber'])->name('settings.tenant.numbers.store');
});

require __DIR__.'/auth.php';
