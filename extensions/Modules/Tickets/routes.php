<?php

use App\Http\Middleware\RequireAdminReauthentication;
use Extensions\Modules\Tickets\Http\Controllers\Admin;
use Extensions\Modules\Tickets\Http\Controllers\Client;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/tickets', [Client\TicketsController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [Client\TicketsController::class, 'create'])->name('tickets.create');
    Route::get('/tickets/guest/{token}', [Client\TicketsController::class, 'guest'])
        ->middleware('throttle:30,1')
        ->name('tickets.guest');

    Route::middleware('auth')->group(function () {
        Route::get('/tickets/{ticket}', [Client\TicketsController::class, 'view'])->name('tickets.view');
    });
});

Route::middleware(['web', 'auth', 'admin', RequireAdminReauthentication::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/tickets', [Admin\TicketsController::class, 'index'])
            ->middleware('permission:admin.tickets')
            ->name('tickets.index');
        Route::get('/tickets/create', [Admin\TicketsController::class, 'create'])
            ->middleware('permission:admin.tickets.create')
            ->name('tickets.create');
        Route::get('/tickets/{ticket}', [Admin\TicketsController::class, 'view'])
            ->middleware('permission:admin.tickets.view')
            ->name('tickets.view');

        Route::get('/ticket-departments', [Admin\TicketDepartmentsController::class, 'index'])
            ->middleware('permission:admin.ticket-departments')
            ->name('ticket-departments.index');
        Route::get('/ticket-departments/create', [Admin\TicketDepartmentsController::class, 'create'])
            ->middleware('permission:admin.ticket-departments.create')
            ->name('ticket-departments.create');
        Route::get('/ticket-departments/{department}/edit', [Admin\TicketDepartmentsController::class, 'edit'])
            ->middleware('permission:admin.ticket-departments.update')
            ->name('ticket-departments.edit');
    });
