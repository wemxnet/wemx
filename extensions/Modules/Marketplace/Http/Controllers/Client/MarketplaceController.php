<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketplaceController extends Controller
{
    public function index()
    {
        return client_view('marketplace::marketplace.index');
    }

    public function author(string $username)
    {
        $author = User::query()->where('username', $username)->firstOrFail();

        $involved = MarketplaceResource::query()
            ->where(function ($query) use ($author) {
                $query->authoredBy($author)
                    ->orWhere(fn ($inner) => $inner->collaboratedBy($author));
            })
            ->exists();

        abort_unless($involved, 404);

        return client_view('marketplace::marketplace.author', [
            'author' => $author,
        ]);
    }

    public function show(MarketplaceCategory $category, MarketplaceResource $resource)
    {
        abort_unless($resource->category_id === $category->id, 404);
        abort_unless($resource->isVisibleTo(auth()->user()), 404);

        return client_view('marketplace::marketplace.show', [
            'category' => $category,
            'resource' => $resource,
        ]);
    }

    public function icon(MarketplaceResource $resource): StreamedResponse
    {
        abort_unless($resource->isVisibleTo(auth()->user()), 404);
        abort_unless($resource->icon_path && $resource->icon_disk, 404);

        return $resource->iconDisk()->response($resource->icon_path);
    }
}
