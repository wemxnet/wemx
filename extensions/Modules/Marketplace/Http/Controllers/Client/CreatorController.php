<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;

class CreatorController extends Controller
{
    public function index()
    {
        return client_view('marketplace::marketplace.studio.index');
    }

    public function create()
    {
        return client_view('marketplace::marketplace.studio.create');
    }

    public function show(MarketplaceResource $resource)
    {
        return client_view('marketplace::marketplace.studio.show', [
            'resource' => $this->authorizeStudioAccess($resource),
        ]);
    }

    public function versions(MarketplaceResource $resource)
    {
        return client_view('marketplace::marketplace.studio.versions', [
            'resource' => $this->authorizeStudioAccess($resource),
        ]);
    }

    public function resourceLicenses(MarketplaceResource $resource)
    {
        return client_view('marketplace::marketplace.studio.resource-licenses', [
            'resource' => $this->authorizeStudioAccess($resource),
        ]);
    }

    public function team(MarketplaceResource $resource)
    {
        return client_view('marketplace::marketplace.studio.team', [
            'resource' => $this->authorizeStudioAccess($resource),
        ]);
    }

    public function sales()
    {
        return client_view('marketplace::marketplace.studio.sales');
    }

    public function licenses()
    {
        return client_view('marketplace::marketplace.studio.licenses');
    }

    public function gateways()
    {
        return client_view('marketplace::marketplace.studio.gateways');
    }

    protected function authorizeStudioAccess(MarketplaceResource $resource): MarketplaceResource
    {
        abort_unless($resource->userCan(auth()->user(), TeamRole::Support), 403);

        return $resource;
    }
}
