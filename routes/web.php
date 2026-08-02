<?php

use Modules\Analytics\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BotTriggerController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Fallback for Windows local development using php artisan serve which struggles with symlinks
if (app()->environment('local') && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    Route::get('/storage/media/{filename}', function ($filename) {
        $path = storage_path('app/public/media/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path);
    });
}

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'      => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'    => PHP_VERSION,
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

    Route::post('/tenants/{tenant}/invites', [\App\Http\Controllers\Admin\TenantInviteController::class, 'store'])->name('tenants.invites.store');
    Route::patch('/tenants/{tenant}/features', [\App\Http\Controllers\Admin\TenantController::class, 'updateFeatures'])->name('tenants.features');

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
    Route::get('/api/conversations', [ChatController::class, 'index'])->name('api.conversations.index');
    Route::post('/api/conversations/{id}/read', [ChatController::class, 'markAsRead'])->name('api.conversations.read');
    Route::get('/api/conversations/{id}/messages', [ChatController::class, 'show'])->name('api.conversations.show');
    Route::post('/api/conversations/{id}/messages', [ChatController::class, 'store']);
    Route::post('/api/conversations/{id}/media', [ChatController::class, 'storeMedia']);

    // Automated Bot Trigger Routes (requires bot_auto_responder feature)
    Route::middleware(['feature:bot_auto_responder'])->group(function () {
        Route::get('/bot-triggers', [BotTriggerController::class, 'index'])->name('bot-triggers.index');
        Route::post('/bot-triggers', [BotTriggerController::class, 'store'])->name('bot-triggers.store');
        Route::put('/bot-triggers/{id}', [BotTriggerController::class, 'update'])->name('bot-triggers.update');
        Route::patch('/bot-triggers/{id}/toggle', [BotTriggerController::class, 'toggleActive'])->name('bot-triggers.toggle');
        Route::delete('/bot-triggers/{id}', [BotTriggerController::class, 'destroy'])->name('bot-triggers.destroy');
    });

    // Contacts & Bulk Messaging Routes (requires contacts_bulk_messaging feature)
    Route::middleware(['feature:contacts_bulk_messaging'])->group(function () {
        Route::get('/contacts', [\App\Http\Controllers\ContactController::class, 'index'])->name('contacts.index');
        Route::post('/contacts/import', [\App\Http\Controllers\ContactController::class, 'import'])->name('contacts.import');
        Route::get('/contacts/{id}', [\App\Http\Controllers\ContactController::class, 'show'])->name('contacts.show');
        Route::put('/contacts/{id}', [\App\Http\Controllers\ContactController::class, 'update'])->name('contacts.update');
        Route::delete('/contacts/{id}', [\App\Http\Controllers\ContactController::class, 'destroy'])->name('contacts.destroy');
        
        Route::get('/campaigns', [\App\Http\Controllers\CampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/campaigns', [\App\Http\Controllers\CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/campaigns/{id}', [\App\Http\Controllers\CampaignController::class, 'show'])->name('campaigns.show');
    });

    // Message Logs Route
    Route::get('/logs', [\App\Http\Controllers\MessageLogController::class, 'index'])->name('logs.index');

    // Flow Builder Routes are registered by the FlowBuilder module directly.

    // Tenant Integration Settings Routes
    Route::get('/settings/tenant', [TenantSettingsController::class, 'edit'])->name('settings.tenant.edit');
    Route::post('/settings/tenant', [TenantSettingsController::class, 'update'])->name('settings.tenant.update');
    Route::post('/settings/tenant/numbers', [TenantSettingsController::class, 'storeNumber'])->name('settings.tenant.numbers.store');
    Route::delete('/settings/tenant/numbers/{number}', [TenantSettingsController::class, 'destroyNumber'])->name('settings.tenant.numbers.destroy');
});

require __DIR__ . '/auth.php';
