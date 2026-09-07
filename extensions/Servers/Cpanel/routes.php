<?php

use App\Http\Middleware\RequireAdminReauthentication;
use Extensions\Servers\Cpanel\Http\Controllers\Admin\LoginController as AdminLoginController;
use Extensions\Servers\Cpanel\Http\Controllers\Client\LoginController as ClientLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/orders/{order}/cpanel/login', ClientLoginController::class)
        ->name('cpanel.login');
});

Route::middleware(['web', 'auth', 'admin', RequireAdminReauthentication::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/orders/{order}/cpanel/login', AdminLoginController::class)
            ->middleware('permission:admin.orders.view')
            ->name('cpanel.login');
    });
