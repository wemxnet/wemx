<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class LibraryController extends Controller
{
    public function purchases()
    {
        return client_view('marketplace::marketplace.library.purchases');
    }

    public function resources()
    {
        return client_view('marketplace::marketplace.library.resources');
    }
}
