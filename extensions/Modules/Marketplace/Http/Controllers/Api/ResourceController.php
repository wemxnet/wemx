<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Models\MarketplaceCategory;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceResource::query()
            ->with(['category', 'author', 'versions'])
            ->integrated();

        $sort = $request->string('sort_by')->toString() ?: 'popular';

        $query = match ($sort) {
            'latest' => $query->latest('published_at')->latest('id'),
            'updated' => $query->orderByLastUpdated(),
            'downloads' => $query->orderByDesc('downloads_count')->orderByDesc('id'),
            'purchases' => $query->orderByDesc('purchases_count')->orderByDesc('id'),
            'popular_free' => $query->where('price', '<=', 0)->popular(),
            'featured' => $query->featured()->popular(),
            default => $query->popular(),
        };

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('category')) {
            $categoryId = MarketplaceCategory::query()
                ->where('slug', $request->string('category')->toString())
                ->value('id');

            $query->where('category_id', $categoryId);
        }

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        $perPage = min(18, max(1, $request->integer('per_page', 18)));

        $resources = $query->paginate($perPage)
            ->through(fn (MarketplaceResource $resource) => $resource->toIntegratedArray(summary: true));

        $showFeatured = $sort === 'popular'
            && ! $request->filled('search')
            && ! $request->filled('category')
            && ! $request->filled('category_id');

        $featured = $showFeatured
            ? MarketplaceResource::query()
                ->with(['category', 'author', 'versions'])
                ->integrated()
                ->featured()
                ->popular()
                ->limit(3)
                ->get()
                ->map(fn (MarketplaceResource $resource) => $resource->toIntegratedArray(summary: true))
                ->all()
            : [];

        $payload = $resources->toArray();
        $payload['categories'] = MarketplaceCategory::query()
            ->visible()
            ->ordered()
            ->get(['slug', 'name'])
            ->map(fn (MarketplaceCategory $category) => [
                'slug' => $category->slug,
                'name' => $category->name,
            ])
            ->all();
        $payload['featured'] = $featured;

        return response()->json($payload);
    }

    public function show(string $slug): JsonResponse
    {
        $resource = MarketplaceResource::query()
            ->with([
                'category',
                'author',
                'versions',
                'reviews' => fn ($query) => $query->visible()->with('user')->latest(),
            ])
            ->integrated()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => $resource->toIntegratedArray(includeReviews: true),
        ]);
    }
}
