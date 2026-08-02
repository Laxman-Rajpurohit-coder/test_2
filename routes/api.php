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
