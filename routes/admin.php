<?php

use App\Http\Controllers\Admin;
use App\Http\Middleware\AdminPathMiddleware;
use Illuminate\Support\Facades\Route;

Route::view('/reauthenticate', 'admin::auth.reauthenticate')->name('reauthenticate');

Route::get('/', [Admin\DashboardController::class, 'index'])->name('index')->middleware('permission:admin.dashboard');

Route::get('/toggle-language/{locale}', [Admin\DashboardController::class, 'toggleLocale'])->name('toggle.language');

Route::group(['prefix' => 'users'], function () {
    Route::get('/', [Admin\UsersController::class, 'index'])->name('users.index')->middleware('permission:admin.users');
    Route::get('/create', [Admin\UsersController::class, 'create'])->name('users.create')->middleware('permission:admin.users.create');
    Route::get('/edit/{user:id}', [Admin\UsersController::class, 'edit'])->name('users.edit')->middleware('permission:admin.users.view');
    Route::get('/edit/{user:id}/impersonate', [Admin\UsersController::class, 'impersonate'])->name('users.impersonate')->middleware('permission:admin.users.impersonate');
    Route::get('/exit-impersonate', [Admin\UsersController::class, 'exitImpersonate'])->name('users.exit-impersonate')->withoutMiddleware(['admin', AdminPathMiddleware::class]);
});

Route::group(['prefix' => 'roles'], function () {
    Route::get('/', [Admin\RolesController::class, 'index'])->name('roles.index')->middleware('permission:admin.roles');
    Route::get('/create', [Admin\RolesController::class, 'create'])->name('roles.create')->middleware('permission:admin.roles.create');
    Route::get('/edit/{role:id}', [Admin\RolesController::class, 'edit'])->name('roles.edit')->middleware('permission:admin.roles.update');
});

Route::group(['prefix' => 'payments'], function () {
    Route::get('/', [Admin\PaymentsController::class, 'index'])->name('payments.index')->middleware('permission:admin.payments');
    Route::get('/create', [Admin\PaymentsController::class, 'create'])->name('payments.create')->middleware('permission:admin.payments.create');
    Route::get('/edit/{payment:id}', [Admin\PaymentsController::class, 'edit'])->name('payments.edit')->middleware('permission:admin.payments.view');
    Route::get('/edit/{payment:id}/invoice-pdf', [Admin\PaymentsController::class, 'downloadInvoicePdf'])->name('payments.invoice-pdf')->middleware('permission:admin.payments.view');
});

Route::group(['prefix' => 'orders'], function () {
    Route::get('/', [Admin\OrdersController::class, 'index'])->name('orders.index')->middleware('permission:admin.orders');
    Route::get('/create', [Admin\OrdersController::class, 'create'])->name('orders.create')->middleware('permission:admin.orders.create');
    Route::get('/edit/{order:id}', [Admin\OrdersController::class, 'edit'])->name('orders.edit')->middleware('permission:admin.orders.view');
});

Route::group(['prefix' => 'subscriptions'], function () {
    Route::get('/', [Admin\SubscriptionsController::class, 'index'])->name('subscriptions.index')->middleware('permission:admin.subscriptions');
    Route::get('/edit/{subscription:id}', [Admin\SubscriptionsController::class, 'edit'])->name('subscriptions.edit')->middleware('permission:admin.subscriptions.view');
});

Route::group(['prefix' => 'categories'], function () {
    Route::get('/', [Admin\CategoriesController::class, 'index'])->name('categories.index');
    Route::get('/create', [Admin\CategoriesController::class, 'create'])->name('categories.create');
    Route::get('/edit/{category:id}', [Admin\CategoriesController::class, 'edit'])->name('categories.edit');
});

Route::group(['prefix' => 'pages'], function () {
    Route::get('/', [Admin\PagesController::class, 'index'])->name('pages.index')->middleware('permission:admin.pages.index');
    Route::get('/create', [Admin\PagesController::class, 'create'])->name('pages.create')->middleware('permission:admin.pages.create');
    Route::get('/view/{page:id}', [Admin\PagesController::class, 'view'])->name('pages.view')->middleware('permission:admin.pages.view');
    Route::get('/edit/{page:id}', [Admin\PagesController::class, 'edit'])->name('pages.edit')->middleware('permission:admin.pages.update');
});

Route::group(['prefix' => 'packages'], function () {
    Route::get('/', [Admin\PackagesController::class, 'index'])->name('packages.index');
    Route::get('/create', [Admin\PackagesController::class, 'create'])->name('packages.create');
    Route::get('/edit/{package:id}', [Admin\PackagesController::class, 'edit'])->name('packages.edit');
});

// TEMPORARY: Admin marketplace disabled — uncomment to restore.
// Route::group(['prefix' => 'marketplace'], function () {
//     Route::get('/', [Admin\MarketplaceController::class, 'index'])->name('marketplace.index');
// });

Route::group(['prefix' => 'gateways'], function () {
    Route::get('/', [Admin\GatewaysController::class, 'index'])->name('gateways.index');
    Route::get('/configs/', [Admin\GatewaysController::class, 'configs'])->name('gateways.configs.index');
    Route::get('/configs/create', [Admin\GatewaysController::class, 'create'])->name('gateways.configs.create');
    Route::get('/configs/edit/{gateway:id}', [Admin\GatewaysController::class, 'edit'])->name('gateways.configs.edit');
});

Route::group(['prefix' => 'emails'], function () {
    Route::get('/', [Admin\EmailsController::class, 'index'])->name('emails.index');
    Route::get('/view/{email:id}', [Admin\EmailsController::class, 'view'])->name('emails.view');
    Route::get('/configure', [Admin\EmailsController::class, 'configure'])->name('emails.configure');
});

Route::group(['prefix' => 'servers'], function () {
    Route::get('/', [Admin\ServersController::class, 'index'])->name('servers.index');
    Route::get('/connections', [Admin\ServersController::class, 'connections'])->name('servers.connections');
    Route::get('/connections/create', [Admin\ServersController::class, 'createConnection'])->name('servers.connections.create');
    Route::get('/connections/edit/{connection:id}', [Admin\ServersController::class, 'editConnection'])->name('servers.connections.edit');
});

Route::group(['prefix' => 'currencies'], function () {
    Route::get('/', [Admin\CurrencyController::class, 'index'])->name('currencies.index');
    Route::get('/create', [Admin\CurrencyController::class, 'create'])->name('currencies.create');
    Route::get('/updates-rates', [Admin\CurrencyController::class, 'updateRates'])->name('currencies.update-rates');
    Route::get('/edit/{currency:currency}', [Admin\CurrencyController::class, 'edit'])->name('currencies.edit');
});

Route::group(['prefix' => 'extensions'], function () {
    Route::get('/', [Admin\ExtensionsController::class, 'index'])->name('extensions.index');
    Route::get('/toggle/{extension:identifier}', [Admin\ExtensionsController::class, 'toggle'])->name('extensions.toggle');
    Route::get('/discover', [Admin\ExtensionsController::class, 'discover'])->name('extensions.discover');
});

Route::group(['prefix' => 'settings'], function () {
    Route::get('/', [Admin\SettingsController::class, 'index'])->name('settings.index');
});

Route::group(['prefix' => 'license'], function () {
    Route::get('/', [Admin\LicenseController::class, 'index'])->name('license.index')->middleware('permission:admin.settings.index');
    Route::post('/verify', [Admin\LicenseController::class, 'verify'])->name('license.verify')->middleware('permission:admin.settings.index');
    Route::post('/update', [Admin\LicenseController::class, 'update'])->name('license.update')->middleware('permission:admin.settings.index');
});

Route::group(['prefix' => 'images'], function () {
    Route::get('/', [Admin\ImagesController::class, 'index'])->name('images.index');
    Route::post('/upload', [Admin\ImagesController::class, 'upload'])->name('images.upload');
});

Route::group(['prefix' => 'taxes'], function () {
    Route::get('/', [Admin\TaxesController::class, 'index'])->name('taxes.index');
    Route::get('/create', [Admin\TaxesController::class, 'create'])->name('taxes.create');
    Route::get('/edit/{country_code}', [Admin\TaxesController::class, 'edit'])->name('taxes.edit');
});

Route::group(['prefix' => 'schedule-logs'], function () {
    Route::get('/', [Admin\ScheduleLogController::class, 'index'])->name('schedule-logs.index');
    Route::get('/view/{log}', [Admin\ScheduleLogController::class, 'view'])->name('schedule-logs.view');
});

Route::get('/updates/database-export', [Admin\UpdatesController::class, 'exportDatabase'])
    ->name('updates.database-export')
    ->middleware('permission:admin.settings.index');

Route::view('/updates', 'admin::updates.index')
    ->name('updates.index')
    ->middleware('permission:admin.settings.index');
