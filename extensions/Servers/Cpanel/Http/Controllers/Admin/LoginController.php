<?php

namespace Extensions\Servers\Cpanel\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\Servers\Cpanel\Server;
use Illuminate\Http\RedirectResponse;

class LoginController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        $session = Server::actions()->loginAsAdmin([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
        ]);

        return redirect()->away($session['url']);
    }
}
