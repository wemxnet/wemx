<?php

namespace Extensions\Servers\Proxmox\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Extensions\Servers\Proxmox\Server;
use Illuminate\Http\RedirectResponse;

class ConsoleController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        if ($order->user_id !== auth()->id()) {
            $isMember = $order->members()
                ->where('status', 'active')
                ->where('user_id', auth()->id())
                ->exists();

            abort_unless($isMember, 403);
        }

        $console = Server::actions()->consoleAsClient([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
        ]);

        return redirect()->away($console['url']);
    }
}
