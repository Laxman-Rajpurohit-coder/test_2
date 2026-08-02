<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // FlowBuilder API Endpoints will be registered here
});
