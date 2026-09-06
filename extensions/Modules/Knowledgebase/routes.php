<?php

use App\Http\Middleware\RequireAdminReauthentication;
use Extensions\Modules\Knowledgebase\Http\Controllers\Admin;
use Extensions\Modules\Knowledgebase\Http\Controllers\Client;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/knowledgebase', [Client\KnowledgebaseController::class, 'index'])->name('knowledgebase.index');
    Route::get('/knowledgebase/search', [Client\KnowledgebaseController::class, 'search'])->name('knowledgebase.search');
    Route::get('/knowledgebase/{category:slug}', [Client\KnowledgebaseController::class, 'category'])->name('knowledgebase.category');
    Route::get('/knowledgebase/{category:slug}/{article:slug}', [Client\KnowledgebaseController::class, 'article'])->name('knowledgebase.article');
});

Route::middleware(['web', 'auth', 'admin', RequireAdminReauthentication::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/knowledgebase', [Admin\KnowledgebaseController::class, 'index'])
            ->middleware('permission:admin.knowledgebase')
            ->name('knowledgebase.index');
        Route::get('/knowledgebase/articles/create', [Admin\KnowledgebaseController::class, 'createArticle'])
            ->middleware('permission:admin.knowledgebase.create')
            ->name('knowledgebase.articles.create');
        Route::get('/knowledgebase/articles/{article}/edit', [Admin\KnowledgebaseController::class, 'editArticle'])
            ->middleware('permission:admin.knowledgebase.update')
            ->name('knowledgebase.articles.edit');

        Route::get('/knowledgebase/categories', [Admin\KnowledgebaseController::class, 'categories'])
            ->middleware('permission:admin.knowledgebase')
            ->name('knowledgebase.categories.index');
        Route::get('/knowledgebase/categories/create', [Admin\KnowledgebaseController::class, 'createCategory'])
            ->middleware('permission:admin.knowledgebase.create')
            ->name('knowledgebase.categories.create');
        Route::get('/knowledgebase/categories/{category}/edit', [Admin\KnowledgebaseController::class, 'editCategory'])
            ->middleware('permission:admin.knowledgebase.update')
            ->name('knowledgebase.categories.edit');
    });
