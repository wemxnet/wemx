<?php

use App\Http\Middleware\RequireAdminReauthentication;
use Extensions\Modules\Marketplace\Http\Controllers\Admin;
use Extensions\Modules\Marketplace\Http\Controllers\Api;
use Extensions\Modules\Marketplace\Http\Controllers\Client;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/marketplace')->middleware('throttle:60,1')->group(function () {
    Route::get('/resources', [Api\ResourceController::class, 'index'])->name('api.marketplace.resources.index');
    Route::get('/resources/download/{version}', [Api\DownloadController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('api.marketplace.resources.download');
    Route::post('/resources/{slug}/view', [Api\ResourceController::class, 'view'])
        ->middleware('throttle:30,1')
        ->name('api.marketplace.resources.view');
    Route::get('/resources/{slug}', [Api\ResourceController::class, 'show'])->name('api.marketplace.resources.show');
});

Route::post('/marketplace/webhooks/{driver}/{config}', [Client\GatewayWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('marketplace.webhooks.handle');

Route::middleware('web')->group(function () {
    Route::get('/marketplace', [Client\MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::get('/marketplace/icons/{resource}', [Client\MarketplaceController::class, 'icon'])->name('marketplace.icons');
    Route::get('/marketplace/authors/{username}', [Client\MarketplaceController::class, 'author'])->name('marketplace.authors.show');

    Route::middleware('auth')->group(function () {
        Route::get('/marketplace/library/purchases', [Client\LibraryController::class, 'purchases'])->name('marketplace.library.purchases');
        Route::get('/marketplace/library/purchases/{license}', [Client\LibraryController::class, 'showPurchase'])->name('marketplace.library.purchases.show');
        Route::get('/marketplace/library/resources', [Client\LibraryController::class, 'resources'])->name('marketplace.library.resources');
        Route::get('/marketplace/studio', [Client\CreatorController::class, 'index'])->name('marketplace.studio.index');
        Route::get('/marketplace/studio/create', [Client\CreatorController::class, 'create'])->name('marketplace.studio.create');
        Route::get('/marketplace/studio/sales', [Client\CreatorController::class, 'sales'])->name('marketplace.studio.sales');
        Route::get('/marketplace/studio/licenses', [Client\CreatorController::class, 'licenses'])->name('marketplace.studio.licenses');
        Route::get('/marketplace/studio/gateways', [Client\CreatorController::class, 'gateways'])->name('marketplace.studio.gateways');
        Route::get('/marketplace/studio/resources/{resource}', [Client\CreatorController::class, 'show'])->name('marketplace.studio.resources.show');
        Route::get('/marketplace/studio/resources/{resource}/versions', [Client\CreatorController::class, 'versions'])->name('marketplace.studio.resources.versions');
        Route::get('/marketplace/studio/resources/{resource}/licenses', [Client\CreatorController::class, 'resourceLicenses'])->name('marketplace.studio.resources.licenses');
        Route::get('/marketplace/studio/resources/{resource}/team', [Client\CreatorController::class, 'team'])->name('marketplace.studio.resources.team');
        Route::post('/marketplace/studio/resources/{resource}/icon', [Client\CreatorController::class, 'updateIcon'])
            ->middleware('throttle:20,1')
            ->name('marketplace.studio.resources.icon.update');
        Route::delete('/marketplace/studio/resources/{resource}/icon', [Client\CreatorController::class, 'destroyIcon'])
            ->middleware('throttle:20,1')
            ->name('marketplace.studio.resources.icon.destroy');

        Route::post('/marketplace/{resource}/purchase', [Client\PurchaseController::class, 'start'])->name('marketplace.purchase');
        Route::get('/marketplace/checkout/{sale}/paypal', [Client\PurchaseController::class, 'paypal'])->name('marketplace.checkout.paypal');
        Route::get('/marketplace/checkout/{sale}/return', [Client\PurchaseController::class, 'returned'])->name('marketplace.checkout.return');
        Route::get('/marketplace/checkout/{sale}/cancel', [Client\PurchaseController::class, 'cancel'])->name('marketplace.checkout.cancel');

        Route::get('/marketplace/versions/{version}/download', [Client\DownloadController::class, 'download'])
            ->middleware('throttle:30,1')
            ->name('marketplace.versions.download');
    });

    Route::get('/marketplace/{category}/{resource}', [Client\MarketplaceController::class, 'show'])->name('marketplace.show');
});

Route::middleware(['web', 'auth', 'admin', RequireAdminReauthentication::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/marketplace-manager', [Admin\MarketplaceController::class, 'index'])
            ->middleware('permission:admin.marketplace')
            ->name('marketplace-manager.index');
        Route::get('/marketplace-manager/resources', [Admin\MarketplaceController::class, 'resources'])
            ->middleware('permission:admin.marketplace.manage')
            ->name('marketplace-manager.resources.index');
        Route::get('/marketplace-manager/resources/{resource}', [Admin\MarketplaceController::class, 'show'])
            ->middleware('permission:admin.marketplace.manage')
            ->name('marketplace-manager.resources.show');
        Route::get('/marketplace-manager/sales', [Admin\MarketplaceController::class, 'sales'])
            ->middleware('permission:admin.marketplace')
            ->name('marketplace-manager.sales.index');
        Route::get('/marketplace-manager/licenses', [Admin\MarketplaceController::class, 'licenses'])
            ->middleware('permission:admin.marketplace')
            ->name('marketplace-manager.licenses.index');
    });
