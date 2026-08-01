<?php

use Illuminate\Support\Facades\Route;
use Modules\FlowBuilder\Http\Controllers\FlowController;

Route::middleware(['web', 'auth', 'verified', 'feature:flow_builder'])->group(function () {
    Route::get('/flows', [FlowController::class, 'index'])->name('flows.index');
    Route::post('/flows', [FlowController::class, 'store'])->name('flows.store');
    Route::get('/flows/{id}', [FlowController::class, 'show'])->name('flows.show');
    Route::put('/flows/{id}', [FlowController::class, 'update'])->name('flows.update');
    Route::delete('/flows/{id}', [FlowController::class, 'destroy'])->name('flows.destroy');
});
