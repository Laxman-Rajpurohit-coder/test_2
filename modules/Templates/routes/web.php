<?php

use Illuminate\Support\Facades\Route;
use Modules\Templates\Http\Controllers\TemplateController;

Route::middleware(['web', 'auth', 'verified', 'feature:template_management', 'role:owner,admin'])
    ->group(function () {
        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{id}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::get('/templates/{id}', [TemplateController::class, 'show'])->name('templates.show');
        Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('/templates/sync', [TemplateController::class, 'sync'])->name('templates.sync');
    });
