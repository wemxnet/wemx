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
            'downloads' => $query->orderByDesc('downloads_count')->orderByDesc('id'),
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

        $resources = $query->paginate($request->integer('per_page', 24))
            ->through(fn (MarketplaceResource $resource) => $resource->toIntegratedArray());

        return response()->json($resources);
    }

    public function show(string $slug): JsonResponse
    {
        $resource = MarketplaceResource::query()
            ->with(['category', 'author', 'versions'])
            ->integrated()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['data' => $resource->toIntegratedArray()]);
    }
}
