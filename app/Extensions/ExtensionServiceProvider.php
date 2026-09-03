<?php

namespace App\Extensions;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class ExtensionServiceProvider extends ServiceProvider
{
    protected array $extensionNamespaces = [];

    public function __construct($app)
    {
        parent::__construct($app);
        $this->extensionNamespaces = $this->getExtensionNamespaces();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ($this->extensionNamespaces as $extensionNamespace) {
            if (class_exists($extensionNamespace)) {
                $extensionClass = new $extensionNamespace;

                if (method_exists($extensionClass, 'providers')) {
                    foreach ($extensionClass->providers() as $provider) {
                        if ($this->app) {
                            $this->app->register($provider);
                        }
                    }
                }
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ($this->extensionNamespaces as $extensionNamespace) {
            if (class_exists($extensionNamespace)) {
                $extensionClass = new $extensionNamespace;

                if ($extensionClass->hasViews()) {
                    $this->loadViewsFrom($extensionClass->getViewsPath(), $extensionClass->getId());
                    Volt::mount($extensionClass->getViewsPath());
                }

                if ($extensionClass->hasTranslations()) {
                    $this->loadTranslationsFrom($extensionClass->getTranslationsPath(), $extensionClass->getId());
                }

                if ($extensionClass->hasMigrations()) {
                    $this->loadMigrationsFrom($extensionClass->getMigrationsPath());
                }

                if ($extensionClass->hasRoutes()) {
                    $this->loadRoutesFrom($extensionClass->getRoutesPath());
                }

                if ($extensionClass->hasConfig()) {
                    $this->mergeConfigFrom($extensionClass->getConfigPath(), $extensionClass->getId());
                }

                if (method_exists($extensionClass, 'commands')) {
                    $this->commands($extensionClass->commands());
                }

                if (method_exists($extensionClass, 'schedule')) {
                    $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($extensionClass) {
                        $extensionClass->schedule($schedule);
                    });
                }
            }
        }
    }

    private function getExtensionNamespaces(): array
    {
        if (! Schema::hasTable('extensions')) {
            return [];
        }

        return DB::table('extensions')
            ->where('status', 'enabled')
            ->pluck('namespace')
            ->toArray() ?? [];
    }
}
