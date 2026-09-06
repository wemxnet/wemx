<?php

use App\Http\Middleware\RequireAdminReauthentication;
use Extensions\Modules\Downloads\Http\Controllers\Admin;
use Extensions\Modules\Downloads\Http\Controllers\Client;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/downloads', [Client\DownloadsController::class, 'index'])->name('downloads.index');
    Route::get('/downloads/{folder:slug}', [Client\DownloadsController::class, 'folder'])->name('downloads.folder');
    Route::get('/downloads/{folder:slug}/{file}/download', [Client\DownloadsController::class, 'download'])
        ->middleware('throttle:60,1')
        ->name('downloads.download');
});

Route::middleware(['web', 'auth', 'admin', RequireAdminReauthentication::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/downloads', [Admin\DownloadsController::class, 'index'])
            ->middleware('permission:admin.downloads')
            ->name('downloads.index');
        Route::get('/downloads/folders/create', [Admin\DownloadsController::class, 'createFolder'])
            ->middleware('permission:admin.downloads.create')
            ->name('downloads.folders.create');
        Route::get('/downloads/folders/{folder}', [Admin\DownloadsController::class, 'showFolder'])
            ->middleware('permission:admin.downloads')
            ->name('downloads.folders.show');
        Route::get('/downloads/folders/{folder}/edit', [Admin\DownloadsController::class, 'editFolder'])
            ->middleware('permission:admin.downloads.update')
            ->name('downloads.folders.edit');
        Route::get('/downloads/folders/{folder}/files/create', [Admin\DownloadsController::class, 'createFile'])
            ->middleware('permission:admin.downloads.create')
            ->name('downloads.files.create');
        Route::get('/downloads/files/{file}/edit', [Admin\DownloadsController::class, 'editFile'])
            ->middleware('permission:admin.downloads.update')
            ->name('downloads.files.edit');
    });
