<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Http\Requests\UploadResourceIconRequest;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Support\MarketplaceLimits;
use Illuminate\Http\RedirectResponse;

class CreatorController extends Controller
{
    public function index()
    {
        return client_view('marketplace::marketplace.studio.index');
    }

    public function create()
    {
        MarketplaceLimits::assertCanCreateResource(auth()->user());

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

    public function updateIcon(UploadResourceIconRequest $request, MarketplaceResource $resource): RedirectResponse
    {
        MarketplaceResource::actions()->uploadIconAsCreator([
            'user_id' => auth()->id(),
            'resource_id' => $resource->id,
            'icon' => $request->file('icon'),
        ]);

        return back()->with('success', 'Icon updated.');
    }

    public function destroyIcon(MarketplaceResource $resource): RedirectResponse
    {
        abort_unless($resource->userCan(auth()->user(), TeamRole::Manager), 403);

        MarketplaceResource::actions()->removeIconAsCreator([
            'user_id' => auth()->id(),
            'resource_id' => $resource->id,
        ]);

        return back()->with('success', 'Icon removed.');
    }

    protected function authorizeStudioAccess(MarketplaceResource $resource): MarketplaceResource
    {
        abort_unless($resource->userCan(auth()->user(), TeamRole::Support), 403);

        return $resource;
    }
}
