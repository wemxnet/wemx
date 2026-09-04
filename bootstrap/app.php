<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminPathMiddleware;
use App\Http\Middleware\CheckActiveUserBan;
use App\Http\Middleware\CheckPendingOrSuspendedUser;
use App\Http\Middleware\CheckPermissionMiddleware;
use App\Http\Middleware\DefineCartMiddleware;
use App\Http\Middleware\ImpersonateUser;
use App\Http\Middleware\InstallAppMiddleware;
use App\Http\Middleware\RequireAddressMiddleware;
use App\Http\Middleware\RequireAdminReauthentication;
use App\Http\Middleware\RequireTFAMiddleware;
use App\Http\Middleware\SetUserLocale;
use App\Http\Middleware\SyncRuntimeMiddleware;
use App\Http\Middleware\VerifyEmailMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth', 'admin', RequireAdminReauthentication::class])
                ->prefix('admin')->name('admin.')
                ->group(base_path('routes/admin.php'));
            Route::middleware(['web'])->prefix('auth')
                ->group(base_path('routes/auth.php'));
        }
    )->withCommands([
        __DIR__.'/../app/Extensions/Commands',
    ])->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'gateways/webhooks/*',
            'gateways/callbacks/*',
            'tickets/inbound-mail',
        ]);

        $middleware->alias([
            'permission' => CheckPermissionMiddleware::class,
            'admin' => AdminMiddleware::class,
        ]);

        $middleware->appendToGroup('web', [
            SetUserLocale::class,
            InstallAppMiddleware::class,
            SyncRuntimeMiddleware::class,
            CheckPendingOrSuspendedUser::class,
            CheckActiveUserBan::class,
            VerifyEmailMiddleware::class,
            RequireAddressMiddleware::class,
            RequireTFAMiddleware::class,
            ImpersonateUser::class,
            DefineCartMiddleware::class,
            AdminPathMiddleware::class,
        ]);

        $middleware->trustProxies(at: '*');
    })->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
