<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Stateless Machine-to-Machine Endpoints)
|--------------------------------------------------------------------------
|
| Registered via bootstrap/app.php under the 'api' middleware group.
| Automatically prefixed with '/api'. No CSRF validation required by design.
|
*/

Route::post('/msg91/webhook', [WebhookController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyMsg91Webhook::class);

// Public Website Widget Embed Script & Lead Submission (CORS Enabled)
Route::get('/widget/v1/{tenant_id}.js', [\App\Http\Controllers\WidgetController::class, 'script']);
Route::options('/v1/widget/{tenant_id}/submit', [\App\Http\Controllers\WidgetController::class, 'options']);
Route::post('/v1/widget/{tenant_id}/submit', [\App\Http\Controllers\WidgetController::class, 'submit'])
    ->middleware('throttle:30,1');

Route::prefix('v1')->middleware([
    'throttle:public-api',
    \App\Http\Middleware\AuthenticatePublicApi::class
])->group(function () {
    Route::post('/contacts', [\App\Http\Controllers\Api\V1\ContactController::class, 'store']);
});
