<?php

use App\Http\Controllers\Client;
use App\Mail\CustomerMail;
use App\Models\Email;
use App\Models\Session;
use App\Models\UserBan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| These routes are accessible by everyone including guests and authenticated users.
|
*/

Route::view('/payments/view/{payment:token}', 'theme::payments.view')->name('payments.view');
Route::get('/payments/view/{payment:token}/invoice-pdf', [Client\PaymentsController::class, 'downloadInvoicePdf'])->name('payments.view.invoice-pdf');

Route::view('/categories', 'theme::categories.index')->name('categories.index');
Route::get('/pages/{page:slug}', [Client\PagesController::class, 'view'])->name('pages.view');

Route::view('/packages/{package:slug}', 'theme::packages.view')->name('packages.view');

Route::view('/cart', 'theme::cart.index')->name('cart');

Route::middleware(['web'])->group(function () {
    Route::get('/payments/pay/{gateway}/{payment:token}', [Client\PaymentsController::class, 'pay'])->name('payments.pay');
    Route::get('/payments/subscribe/{gateway}/{subscription:token}', [Client\PaymentsController::class, 'subscribe'])->name('payments.subscribe');
    Route::any('/gateways/webhooks/{webhook_id}', [Client\PaymentsController::class, 'gatewayWebhook'])->name('payments.gateway.webhook');
    Route::any('/gateways/callbacks/{webhook_id}', [Client\PaymentsController::class, 'gatewayCallback'])->name('payments.gateway.callback');

    // Paddle's "default payment link": Paddle redirects here with ?_ptxn={transaction_id}
    // appended, and Paddle.js (loaded on this page) opens its checkout overlay so the
    // customer can actually enter payment details.
    Route::get('/payments/paddle/checkout', [Client\PaymentsController::class, 'paddleCheckout'])->name('payments.paddle.checkout');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| These routes require the user to be authenticated.
|
*/

Route::middleware(['web', 'auth'])->group(function () {
    Route::view('/', 'theme::dashboard.index')->name('dashboard');
    Route::view('/payments', 'theme::dashboard.payments')->name('dashboard.payments');
    Route::view('/balance', 'theme::dashboard.balance')->name('dashboard.balance');
    Route::view('/order-invites', 'theme::dashboard.order-invites')->name('dashboard.order-invites');

    Route::get('/email-inbox', function () {
        // mark all emails where seen_at is null as seen
        auth()->user()->emails()->whereNull('seen_at')->update(['seen_at' => now()]);

        return view('theme::dashboard.email-inbox');
    })->name('dashboard.email-inbox');

    Route::view('/payments/payment-completed', 'theme::payments.payment-completed')->name('payments.completed');
    Route::view('/payments/payment-cancelled', 'theme::payments.payment-cancelled')->name('payments.cancelled');

    Route::view('/invoices/{invoice:token}', 'theme::invoices.view')->name('invoices.view');

    Route::view('/account/settings', 'theme::account.settings')->name('account.settings');

    Route::view('/account-pending', 'theme::auth.account-pending')->name('account-pending');
    Route::get('/account-suspended', function () {
        $user = auth()->user();
        $activeBan = $user?->activeBan();

        if (! $activeBan) {
            $sessionIp = Session::query()
                ->where('user_id', $user->id)
                ->whereNotNull('ip_address')
                ->latest('last_activity')
                ->value('ip_address');

            if ($sessionIp) {
                $activeBan = UserBan::query()
                    ->where('is_ip_ban', true)
                    ->where('ip_address', $sessionIp)
                    ->whereNull('lifted_at')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->latest()
                    ->first();
            }
        }

        if (! $activeBan) {
            return redirect()->route('dashboard');
        }

        return view('theme::auth.account-suspended', [
            'ban' => $activeBan,
        ]);
    })->name('account-suspended');

    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [Client\SubscriptionsController::class, 'index'])->name('subscriptions.index');
        Route::get('/cancel/{subscription:id}', [Client\SubscriptionsController::class, 'cancel'])->name('subscriptions.cancel');
    });

    Route::prefix('orders')->group(function () {
        Route::get('/view/{order:id}', [Client\OrdersController::class, 'view'])->name('orders.view');
        Route::get('/view/{order:id}/payments', [Client\OrdersController::class, 'payments'])->name('orders.view.payments');
        Route::get('/view/{order:id}/emails', [Client\OrdersController::class, 'emails'])->name('orders.view.emails');
        Route::get('/view/{order:id}/members', [Client\OrdersController::class, 'members'])->name('orders.view.members');

        Route::get('/view/{order:id}/subscription', [Client\OrdersController::class, 'subscription'])->name('orders.view.subscription');
        Route::get('/view/{order:id}/subscription/subscribe/{gateway_id}', [Client\OrdersController::class, 'subscribe'])->name('orders.view.subscription.subscribe');

        Route::get('/view/invites/accept', [Client\OrdersController::class, 'acceptInvite'])->name('orders.invites.accept');
        Route::get('/view/invites/reject', [Client\OrdersController::class, 'rejectInvite'])->name('orders.invites.reject');
        Route::get('/view/invites/remove', [Client\OrdersController::class, 'removeMember'])->name('orders.invites.remove');
    });

    Route::get('/emails/view/{email:id}', function (Email $email) {
        abort_if($email->user_id !== auth()->id(), 404);
        abort_if(! $email->display, 404);

        return new CustomerMail($email);
    })->name('emails.view');
});
