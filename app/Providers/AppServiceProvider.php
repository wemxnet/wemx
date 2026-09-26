<?php

namespace App\Providers;

use App\Extensions\ExtensionServiceProvider;
use App\Install\InstallServiceProvider;
use App\Invoices\InvoiceTheme;
use App\Mail\EmailTheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // If app is not installed, add the install provider
        if (! config('app.installed', false)) {
            $this->app->register(InstallServiceProvider::class);
        } else {
            // If app is installed, add the extension provider
            $this->app->register(ExtensionServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prohibits: db:wipe, migrate:fresh, migrate:refresh, and migrate:reset
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Enable automatic eager loading of relationships
        Model::automaticallyEagerLoadRelationships();

        // force https
        if (config('app.force_https', false)) {
            \URL::forceScheme('https');
        }

        // set the default locale and currency
        Number::useLocale('en');
        Number::useCurrency('USD');

        // register custom client theme
        $this->registerClientTheme();

        // register custom admin theme
        $this->registerAdminTheme();

        // register installed email themes
        $this->registerEmailThemes();

        // register installed invoice themes
        $this->registerInvoiceThemes();

        // define @settings('key') directive
        Blade::directive('settings', function ($key, $default = null) {
            return "<?php echo settings($key, $default); ?>";
        });

        // define @has('permission') directive
        $this->registerPermissionsDirective();
    }

    /**
     * Register the client area theme
     */
    private function registerClientTheme(): void
    {
        // check if the theme directory exists
        if (! is_dir(resource_path('client_area/'.config('app.theme', 'default')))) {
            throw new \RuntimeException('Client theme "'.config('app.theme', 'default').'" not found');
        }

        $this->loadViewsFrom(resource_path('client_area/'.config('app.theme', 'default')), 'theme');

        // php artisan vendor:publish --tag=client - Publishes the client theme assets
        $this->publishes([
            resource_path('client_area/'.config('app.theme', 'default').'/assets') => public_path('assets/clientarea/'.config('app.theme', 'default')),
        ], 'client');
    }

    /**
     * Register the admin area theme
     */
    private function registerAdminTheme(): void
    {
        // check if the theme directory exists
        if (! is_dir(resource_path('admin_area/'.config('app.admin_theme', 'default')))) {
            throw new \RuntimeException('Admin theme "'.config('app.admin_theme', 'default').'" not found');
        }

        $this->loadViewsFrom(resource_path('admin_area/'.config('app.admin_theme', 'default')), 'admin');

        // php artisan vendor:publish --tag=admin - Publishes the admin theme assets
        $this->publishes([
            resource_path('admin_area/'.config('app.admin_theme', 'default').'/assets') => public_path('assets/adminarea/'.config('app.admin_theme', 'default')),
        ], 'admin');
    }

    /**
     * Register every invoice theme installed under resources/invoices.
     */
    private function registerInvoiceThemes(): void
    {
        if (! is_dir(resource_path('invoices'))) {
            throw new \RuntimeException('Invoice themes directory not found.');
        }

        foreach (InvoiceTheme::all() as $theme) {
            $this->loadViewsFrom($theme->path, $theme->namespace());
        }
    }

    /**
     * Register every email theme installed under resources/email_templates.
     */
    private function registerEmailThemes(): void
    {
        if (! is_dir(resource_path('email_templates'))) {
            throw new \RuntimeException('Email themes directory not found.');
        }

        foreach (EmailTheme::all() as $theme) {
            $this->loadViewsFrom($theme->path, $theme->namespace());
        }
    }

    /**
     * Register the permissions directive
     */
    private function registerPermissionsDirective(): void
    {
        Blade::directive('perm', function ($permission) {
            return "<?php if(auth()->user()->hasPermission($permission)): ?>";
        });

        Blade::directive('endperm', function () {
            return '<?php endif; ?>';
        });
    }
}
