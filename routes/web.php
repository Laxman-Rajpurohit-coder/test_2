<?php

use Modules\Analytics\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BotTriggerController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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




// Public Website Widget Embed Script
Route::get('/widget/v1/{tenant_id}.js', [\App\Http\Controllers\WidgetController::class, 'script']);

// Meta (Facebook & Instagram) Webhooks (Public with Rate Limiting)
Route::middleware(['throttle:120,1'])->group(function () {
    Route::get('/webhooks/meta', [\App\Http\Controllers\MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
    Route::post('/webhooks/meta', [\App\Http\Controllers\MetaWebhookController::class, 'handle'])->name('webhooks.meta.handle');
});

Route::get('/test-login', function () {
    if (app()->environment('local')) {
        auth()->loginUsingId(9);
        return redirect('/contacts');
    }
});

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'      => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'    => PHP_VERSION,
    ]);
});

// Master SAAS Dashboard Route (Points to Modules\Analytics\Http\Controllers\AnalyticsController)
Route::get('/dashboard', [\Modules\Analytics\Http\Controllers\AnalyticsController::class, 'index'])
    ->middleware(['auth:web,admin', 'verified'])->name('dashboard');

// Admin Auth Routes
Route::get('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'store'])->name('admin.login.store');
Route::match(['get', 'post'], '/admin/logout', [\App\Http\Controllers\Admin\AuthController::class, 'destroy'])->name('admin.logout');

Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tenants', [\App\Http\Controllers\Admin\TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [\App\Http\Controllers\Admin\TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}/stats', [\App\Http\Controllers\Admin\TenantController::class, 'stats'])->name('tenants.stats');
    Route::patch('/tenants/{tenant}/status', [\App\Http\Controllers\Admin\TenantController::class, 'updateStatus'])->name('tenants.status');
    Route::delete('/tenants/{tenant}', [\App\Http\Controllers\Admin\TenantController::class, 'destroy'])->name('tenants.destroy');

    Route::post('/tenants/{tenant}/invites', [\App\Http\Controllers\Admin\TenantInviteController::class, 'store'])->name('tenants.invites.store');
    Route::patch('/tenants/{tenant}/features', [\App\Http\Controllers\Admin\TenantController::class, 'updateFeatures'])->name('tenants.features');

    Route::get('/tenants/{tenant}/credentials', [\App\Http\Controllers\Admin\TenantCredentialController::class, 'show'])->name('tenants.credentials.show');
    Route::patch('/tenants/{tenant}/credentials', [\App\Http\Controllers\Admin\TenantCredentialController::class, 'update'])->name('tenants.credentials.update');

    Route::post('/impersonate/{tenant}', [\App\Http\Controllers\Admin\ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::post('/impersonate-stop', [\App\Http\Controllers\Admin\ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('/billing-settings', [\App\Http\Controllers\Admin\TenantController::class, 'updateBillingSettings'])->name('billing.update');
});

Route::middleware(['auth:web,admin', \App\Http\Middleware\BlockImpersonationWrites::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Coming Soon Route
    Route::get('/coming-soon', function () {
        return Inertia::render('ComingSoon');
    })->name('coming-soon');

    // Chat Application Routes (Inbox per Channel)
    Route::get('/chat', fn() => redirect('/chat/whatsapp'))->name('chat');
    Route::get('/chat/{channel}', [ChatController::class, 'view'])->name('chat.channel')->where('channel', 'whatsapp|facebook|instagram|all');
    Route::get('/api/conversations', [ChatController::class, 'index'])->name('api.conversations.index');
    Route::post('/api/conversations/{id}/read', [ChatController::class, 'markAsRead'])->name('api.conversations.read');
    Route::post('/api/conversations/{id}/favorite', [ChatController::class, 'toggleFavorite'])->name('api.conversations.favorite');
    Route::get('/api/conversations/{id}/messages', [ChatController::class, 'show'])->name('api.conversations.show');
    Route::post('/api/conversations/{id}/messages', [ChatController::class, 'store']);
    Route::post('/api/conversations/{id}/media', [ChatController::class, 'storeMedia']);
    Route::post('/api/conversations/{id}/meta-message', [\App\Http\Controllers\MetaWebhookController::class, 'send'])->name('api.conversations.meta-message');
    Route::post('/api/conversations/bulk-delete', [ChatController::class, 'bulkDelete'])->name('api.conversations.bulk-delete');

    // Customer Tasks & Reminders
    Route::get('/api/conversations/{id}/tasks', [\App\Http\Controllers\CustomerTaskController::class, 'index'])->name('api.conversations.tasks');
    Route::post('/tasks', [\App\Http\Controllers\CustomerTaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{id}/status', [\App\Http\Controllers\CustomerTaskController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('/tasks/{id}', [\App\Http\Controllers\CustomerTaskController::class, 'destroy'])->name('tasks.destroy');

    // Automated Bot Trigger Routes (requires bot_auto_responder feature)
    Route::middleware(['feature:bot_auto_responder', 'role:owner,admin'])->group(function () {
        Route::get('/bot-triggers', [BotTriggerController::class, 'index'])->name('bot-triggers.index');
        Route::post('/bot-triggers', [BotTriggerController::class, 'store'])->name('bot-triggers.store');
        Route::put('/bot-triggers/{id}', [BotTriggerController::class, 'update'])->name('bot-triggers.update');
        Route::patch('/bot-triggers/{id}/toggle', [BotTriggerController::class, 'toggleActive'])->name('bot-triggers.toggle');
        Route::delete('/bot-triggers/{id}', [BotTriggerController::class, 'destroy'])->name('bot-triggers.destroy');
    });

    // Contacts & Bulk Messaging Routes (requires contacts_bulk_messaging feature)
    Route::middleware(['feature:contacts_bulk_messaging'])->group(function () {
        Route::post('/contacts/bulk-assign', [\App\Http\Controllers\ContactController::class, 'bulkAssign'])
            ->middleware('role:owner')
            ->name('contacts.bulk-assign');
            
        Route::post('/contacts/bulk-assign-all', [\App\Http\Controllers\ContactController::class, 'bulkAssignAll'])
            ->middleware('role:owner')
            ->name('contacts.bulk-assign-all');
            
        Route::post('/contacts/bulk-tag', [\App\Http\Controllers\ContactController::class, 'bulkTag'])
            ->name('contacts.bulk-tag');
            
        Route::post('/contacts/quick-send', [\App\Http\Controllers\ContactController::class, 'quickSend'])
            ->name('contacts.quick-send');
            
        Route::post('/contacts/bulk-delete', [\App\Http\Controllers\ContactController::class, 'bulkDelete'])
            ->name('contacts.bulk-delete');

        Route::apiResource('contact-tags', \App\Http\Controllers\ContactTagController::class);
            
        Route::get('/contacts', [\App\Http\Controllers\ContactController::class, 'index'])->name('contacts.index');
        Route::post('/contacts', [\App\Http\Controllers\ContactController::class, 'store'])->name('contacts.store');
        Route::post('/contacts/import', [\App\Http\Controllers\ContactController::class, 'import'])->name('contacts.import');
        Route::get('/contacts/{id}', [\App\Http\Controllers\ContactController::class, 'show'])->name('contacts.show');
        Route::put('/contacts/{id}', [\App\Http\Controllers\ContactController::class, 'update'])->name('contacts.update');
        Route::delete('/contacts/{id}', [\App\Http\Controllers\ContactController::class, 'destroy'])->name('contacts.destroy');
        
        Route::get('/campaigns', [\App\Http\Controllers\CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/create', [\App\Http\Controllers\CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campaigns/recipient-count', [\App\Http\Controllers\CampaignController::class, 'recipientCount'])->name('campaigns.recipient-count');
        Route::post('/campaigns', [\App\Http\Controllers\CampaignController::class, 'store'])->name('campaigns.store');
        Route::post('/campaigns/{id}/cancel', [\App\Http\Controllers\CampaignController::class, 'cancel'])->name('campaigns.cancel');
        Route::get('/campaigns/{id}', [\App\Http\Controllers\CampaignController::class, 'show'])->name('campaigns.show');
    });

    // Message Logs Route
    Route::get('/logs', [\App\Http\Controllers\MessageLogController::class, 'index'])->name('logs.index');
    Route::post('/settings/billing', [\App\Http\Controllers\MessageLogController::class, 'updateBillingSettings'])->name('settings.billing.update');

    // Flow Builder Routes are registered by the FlowBuilder module directly.

    // Tenant Integration Settings Routes
    Route::middleware(['role:owner,admin'])->group(function () {
        Route::get('/settings/tenant', [TenantSettingsController::class, 'edit'])->name('settings.tenant.edit');
        Route::post('/settings/tenant', [TenantSettingsController::class, 'update'])->name('settings.tenant.update');
        Route::post('/settings/tenant/api-key', [TenantSettingsController::class, 'regenerateApiKey'])->name('settings.tenant.api-key');
        Route::post('/settings/tenant/meta-credentials', [TenantSettingsController::class, 'updateMetaCredentials'])->name('settings.tenant.meta-credentials');
        Route::get('/settings/tenant/business-profile', [TenantSettingsController::class, 'getBusinessProfile'])->name('settings.tenant.business-profile.get');
        Route::post('/settings/tenant/business-profile', [TenantSettingsController::class, 'updateBusinessProfile'])->name('settings.tenant.business-profile');
        Route::post('/settings/tenant/numbers', [TenantSettingsController::class, 'storeNumber'])->name('settings.tenant.numbers.store');
        Route::delete('/settings/tenant/numbers/{number}', [TenantSettingsController::class, 'destroyNumber'])->name('settings.tenant.numbers.destroy');
    });
    
    // Team Management Routes
    Route::middleware(['role:owner,admin'])->group(function () {
        Route::get('/team', [\App\Http\Controllers\TeamController::class, 'index'])->name('team.index');
    });
    Route::middleware(['role:owner'])->group(function () {
        Route::post('/team', [\App\Http\Controllers\TeamController::class, 'store'])->name('team.store');
        Route::put('/team/{user}/role', [\App\Http\Controllers\TeamController::class, 'updateRole'])->name('team.role.update');
        Route::delete('/team/{user}', [\App\Http\Controllers\TeamController::class, 'removeUser'])->name('team.remove');
        Route::post('/team/{user}/reset-password', [\App\Http\Controllers\TeamController::class, 'resetPassword'])->name('team.reset-password');
    });
});

require __DIR__ . '/auth.php';
