<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class IntegratedMarketplaceController extends Controller
{
    public function index(): Factory|View
    {
        return admin_view('integrated-marketplace.index');
    }

    public function installed(): Factory|View
    {
        return admin_view('integrated-marketplace.installed');
    }

    public function show(string $slug): Factory|View
    {
        return admin_view('integrated-marketplace.show', [
            'slug' => $slug,
        ]);
    }
}
