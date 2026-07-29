<?php

use Illuminate\Support\Facades\Route;
use Modules\AiBot\Http\Controllers\AiBotController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::get('/ai-bot', [AiBotController::class, 'show'])->name('ai-bot.show');
    Route::put('/ai-bot', [AiBotController::class, 'update'])->name('ai-bot.update');
});
