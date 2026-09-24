<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;

class LibraryController extends Controller
{
    public function purchases()
    {
        return client_view('marketplace::marketplace.library.purchases');
    }

    public function showPurchase(MarketplaceLicense $license)
    {
        abort_unless($license->belongsToUser(auth()->user()), 404);

        return client_view('marketplace::marketplace.library.purchase-show', [
            'license' => $license,
        ]);
    }

    public function resources()
    {
        return client_view('marketplace::marketplace.library.resources');
    }
}
