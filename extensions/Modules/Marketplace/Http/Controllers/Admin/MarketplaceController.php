<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;

class MarketplaceController extends Controller
{
    public function index()
    {
        return admin_view('marketplace::marketplace.index');
    }

    public function resources()
    {
        return admin_view('marketplace::marketplace.manage.index');
    }

    public function show(MarketplaceResource $resource)
    {
        return admin_view('marketplace::marketplace.manage.show', [
            'resource' => $resource,
        ]);
    }

    public function sales()
    {
        return admin_view('marketplace::marketplace.sales');
    }

    public function licenses()
    {
        return admin_view('marketplace::marketplace.licenses');
    }
}
