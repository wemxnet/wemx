<?php

namespace App\Install;

use App\Install\Livewire\InstallWizard;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class InstallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('app.installed', false)) {
            config([
                'session.driver' => env('SESSION_DRIVER_INSTALL', 'file'),
                'cache.default' => env('CACHE_STORE_INSTALL', 'file'),
                // The default cookie name is derived from APP_NAME, which the installer
                // changes on the settings step. Pin it so the session survives that change.
                'session.cookie' => env('SESSION_COOKIE', 'wemx_installer_session'),
            ]);
        }
    }

    public function boot(): void
    {
        Livewire::component('install-wizard', InstallWizard::class);
        $this->loadViewsFrom(__DIR__.'/views', 'install');
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
