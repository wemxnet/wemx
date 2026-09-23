<?php

namespace Extensions\Modules\Marketplace\Actions;

use App\Actions\Action;
use Extensions\Modules\Marketplace\Actions\Concerns\AuthorizesMarketplaceStaff;
use Extensions\Modules\Marketplace\Enums\LicenseStatus;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MarketplaceReviewActions extends Action
{
    use AuthorizesMarketplaceStaff;

    public function upsertAsClient(array $input): MarketplaceResourceReview
    {
        $validated = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_id' => ['required', 'integer', 'exists:marketplace_resources,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ])->validate();

        $user = $this->user((int) $validated['user_id']);
        $resource = MarketplaceResource::findOrFail($validated['resource_id']);

        if (! $resource->isListedPublicly()) {
            throw ValidationException::withMessages([
                'resource_id' => 'You can only review published resources.',
            ]);
        }

        if (! $resource->isFree()) {
            $hasLicense = MarketplaceLicense::query()
                ->where('resource_id', $resource->id)
                ->where('user_id', $user->id)
                ->where('status', LicenseStatus::Active)
                ->exists();

            if (! $hasLicense) {
                throw ValidationException::withMessages([
                    'resource_id' => 'Only customers who purchased this resource can leave a review.',
                ]);
            }
        }

        $review = DB::transaction(function () use ($resource, $user, $validated) {
            $review = MarketplaceResourceReview::query()->updateOrCreate(
                [
                    'resource_id' => $resource->id,
                    'user_id' => $user->id,
                ],
                [
                    'rating' => $validated['rating'],
                    'title' => $validated['title'] ?? null,
                    'body' => $validated['body'],
                    'is_visible' => true,
                ]
            );

            $this->refreshResourceStats($resource);

            return $review->fresh(['user']);
        });

        return $review;
    }

    public function deleteAsClient(array $input): bool
    {
        $validated = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'review_id' => ['required', 'integer', 'exists:marketplace_resource_reviews,id'],
        ])->validate();

        $user = $this->user((int) $validated['user_id']);
        $review = MarketplaceResourceReview::query()->with('resource')->findOrFail($validated['review_id']);

        if ((int) $review->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'review_id' => 'You can only delete your own review.',
            ]);
        }

        $resource = $review->resource;
        $review->delete();
        $this->refreshResourceStats($resource);

        return true;
    }

    protected function refreshResourceStats(MarketplaceResource $resource): void
    {
        $stats = MarketplaceResourceReview::query()
            ->where('resource_id', $resource->id)
            ->visible()
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(AVG(rating), 0) as aggregate_avg')
            ->first();

        $resource->update([
            'reviews_count' => (int) ($stats->aggregate_count ?? 0),
            'reviews_avg' => round((float) ($stats->aggregate_avg ?? 0), 2),
        ]);
    }
}
