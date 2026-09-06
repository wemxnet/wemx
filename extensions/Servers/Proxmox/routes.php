<?php

use Extensions\Servers\Proxmox\Http\Controllers\Client\ConsoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/orders/{order}/proxmox/console', ConsoleController::class)
        ->name('proxmox.console');
});
