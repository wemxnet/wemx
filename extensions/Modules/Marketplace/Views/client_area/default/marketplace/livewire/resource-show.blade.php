<?php

use Extensions\Modules\Marketplace\Enums\ResourceStatus;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceLicense;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceReview;
use Extensions\Modules\Marketplace\Models\MarketplaceSale;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    public int $resourceId;

    #[Url(as: 'tab')]
    public string $tab = 'details';

    public int $rating = 5;

    public string $review_title = '';

    public string $review_body = '';

    public function mount(): void
    {
        abort_unless($this->resource->isVisibleTo(auth()->user()), 404);

        if (! in_array($this->tab, ['details', 'versions', 'reviews'], true)) {
            $this->tab = 'details';
        }

        $existing = $this->myReview;
        if ($existing) {
            $this->rating = $existing->rating;
            $this->review_title = (string) $existing->title;
            $this->review_body = $existing->body;
        }

        MarketplaceResource::actions()->recordView($this->resource, auth()->user());
        unset($this->resource, $this->myReview);
    }

    #[Computed]
    public function resource(): MarketplaceResource
    {
        return MarketplaceResource::query()
            ->with(['category', 'author', 'versions', 'gatewayConfigs', 'teamMembers.user'])
            ->findOrFail($this->resourceId);
    }

    #[Computed]
    public function myReview(): ?MarketplaceResourceReview
    {
        if (! auth()->check()) {
            return null;
        }

        return MarketplaceResourceReview::query()
            ->where('resource_id', $this->resourceId)
            ->where('user_id', auth()->id())
            ->first();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['details', 'versions', 'reviews'], true) ? $tab : 'details';
    }

    public function saveReview(): void
    {
        MarketplaceResourceReview::actions()->upsertAsClient([
            'user_id' => auth()->id(),
            'resource_id' => $this->resourceId,
            'rating' => $this->rating,
            'title' => $this->review_title ?: null,
            'body' => $this->review_body,
        ]);

        unset($this->resource, $this->myReview);
        session()->flash('success', 'Thanks for your review.');
    }

    public function deleteReview(): void
    {
        $review = $this->myReview;

        if (! $review) {
            return;
        }

        MarketplaceResourceReview::actions()->deleteAsClient([
            'user_id' => auth()->id(),
            'review_id' => $review->id,
        ]);

        $this->reset(['rating', 'review_title', 'review_body']);
        $this->rating = 5;
        unset($this->resource, $this->myReview);
        session()->flash('success', 'Your review was removed.');
    }

    public function buy(?int $gatewayConfigId = null): void
    {
        try {
            $sale = MarketplaceSale::actions()->startCheckout([
                'user_id' => auth()->id(),
                'resource_id' => $this->resourceId,
                'gateway_config_id' => $gatewayConfigId,
            ]);
        } catch (ValidationException $exception) {
            session()->flash('error', collect($exception->errors())->flatten()->first() ?: 'Checkout could not be started.');

            return;
        }

        $sale->load(['resource.category', 'gatewayConfig']);

        if (! $sale->gatewayConfig?->is_enabled) {
            session()->flash('error', 'The creator has not configured a payment method yet.');

            return;
        }

        try {
            $response = $sale->gatewayConfig->driver()->checkout($sale, $sale->gatewayConfig);
            $target = method_exists($response, 'getTargetUrl')
                ? $response->getTargetUrl()
                : $response->headers->get('Location');

            if (! $target) {
                session()->flash('error', 'Checkout could not be started. Please try again later.');

                return;
            }

            $this->redirect($target, navigate: false);
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('error', 'Checkout could not be started. Please try again later.');
        }
    }
}

?>

@php
    $resource = $this->resource;
    $user = auth()->user();
    $canManage = $user && $resource->userCan($user, TeamRole::Support);
    $license = $user
        ? MarketplaceLicense::query()->where('resource_id', $resource->id)->where('user_id', $user->id)->active()->first()
        : null;
    $versions = $resource->versions;
    $latest = $resource->isListedPublicly()
        ? ($resource->latestApprovedVersion() ?? $resource->latestVersion())
        : $resource->latestApprovedVersion();
    $paymentMethods = $resource->gatewayConfigs->where('is_enabled', true)->values();
    $hasGateway = $paymentMethods->isNotEmpty();
    $canReview = $resource->canBeReviewedBy($user);
    $canDownloadVersions = $resource->isFree() || $license || $canManage;
    $myReview = $this->myReview;
    $reviews = MarketplaceResourceReview::query()
        ->with('user')
        ->where('resource_id', $resource->id)
        ->visible()
        ->latest()
        ->get();
    $tab = $this->tab;
@endphp

<article>
    <nav class="mb-6 text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('marketplace.index') }}" wire:navigate class="hover:text-gray-900 dark:hover:text-white">Marketplace</a>
        <span class="px-1.5">/</span>
        <span>{{ $resource->category?->name }}</span>
        <span class="px-1.5">/</span>
        <span class="text-gray-900 dark:text-white">{{ $resource->name }}</span>
    </nav>

    @if(session('success'))
        <x-theme::alert.success :text="session('success')" />
    @endif
    @if(session('error'))
        <x-theme::alert.danger :text="session('error')" />
    @endif
    @if(session('pending'))
        <x-theme::alert.warning :text="session('pending')" />
    @endif

    @if($resource->status !== ResourceStatus::Approved)
        <x-theme::alert.warning :text="__('marketplace::messages.pending_approval')" />
    @elseif($resource->is_disabled)
        <x-theme::alert.warning text="This resource is disabled and hidden from the marketplace. Existing buyers can still access it." />
    @endif

    @if($resource->status === ResourceStatus::Rejected && $resource->rejection_reason)
        <x-theme::alert.danger :text="$resource->rejection_reason" />
    @endif

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0">
            <div class="flex items-start gap-4">
                <x-marketplace::resource-icon :resource="$resource" size="lg" />
                <div class="min-w-0">
                    <div class="mb-2 flex flex-wrap gap-2">
                        <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">{{ $resource->category?->name }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $resource->isFree() ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200' }}">{{ $resource->formattedPrice() }}</span>
                        @if($resource->isFeaturedNow())
                            <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-200">Featured</span>
                        @endif
                        @if($resource->is_official)
                            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900/30 dark:text-sky-200">Official</span>
                        @endif
                        @if($latest)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">v{{ $latest->version }}</span>
                        @endif
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $resource->name }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $resource->short_description }}</p>
                    @if($resource->reviews_count > 0)
                        <div class="mt-3">
                            <x-marketplace::star-rating :rating="$resource->reviews_avg" :count="$resource->reviews_count" />
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-8 border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex flex-wrap gap-6" aria-label="Resource sections">
                    @foreach([
                        'details' => 'Details',
                        'versions' => 'Versions'.($versions->isNotEmpty() ? ' ('.$versions->count().')' : ''),
                        'reviews' => 'Reviews'.($resource->reviews_count ? ' ('.$resource->reviews_count.')' : ''),
                    ] as $key => $label)
                        <button
                            type="button"
                            wire:click="setTab('{{ $key }}')"
                            class="border-b-2 pb-3 text-sm font-medium transition {{ $tab === $key ? 'border-primary-600 text-primary-700 dark:border-primary-400 dark:text-primary-300' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}"
                        >{{ $label }}</button>
                    @endforeach
                </nav>
            </div>

            @if($tab === 'details')
                <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Description</h2>
                    <div class="format format-blue dark:format-invert max-w-none">
                        {!! $resource->renderedDescription() !!}
                    </div>
                </div>
            @elseif($tab === 'versions')
                <div class="mt-6">
                    @forelse($versions as $index => $version)
                        <div wire:key="version-{{ $version->id }}" class="relative flex gap-4 pb-8 last:pb-0">
                            <div class="flex w-4 shrink-0 flex-col items-center">
                                <div class="mt-1.5 h-3 w-3 rounded-full {{ $index === 0 ? 'bg-primary-600 ring-4 ring-primary-100 dark:bg-primary-400 dark:ring-primary-900/40' : 'bg-gray-300 dark:bg-gray-600' }}"></div>
                                @if(! $loop->last)
                                    <div class="mt-2 w-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $version->name }}</h3>
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 font-mono text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">v{{ $version->version }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $version->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                            · WemX {{ $version->wemx_version }}
                                            · {{ $version->humanSize() }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($index === 0)
                                            <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">Latest</span>
                                        @endif
                                        @if($version->available_on_integrated_marketplace)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">One-click install</span>
                                        @endif
                                        @if($canDownloadVersions && $version->downloadableFromExtensionMarketplace($resource))
                                            @auth
                                                <x-theme::button.primary href="{{ route('marketplace.versions.download', $version) }}" class="!px-3 !py-1.5 text-xs">
                                                    Download
                                                </x-theme::button.primary>
                                            @else
                                                <a href="{{ route('login') }}" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-400">Sign in to download</a>
                                            @endauth
                                        @endif
                                    </div>
                                </div>
                                <div class="format format-blue dark:format-invert mt-3 max-w-none">{!! $version->renderedChangelog() !!}</div>
                            </div>
                            @if($version->integrated_marketplace_only)
                                <p class="mt-2 text-sm text-yellow-600 dark:text-yellow-400">This version can only be installed through the integrated marketplace.</p>
                            @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No versions published yet.</p>
                    @endforelse
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @auth
                        @if($canReview)
                            <x-theme::card>
                                <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">{{ $myReview ? 'Update your review' : 'Write a review' }}</h2>
                                <form wire:submit="saveReview" class="space-y-3">
                                    <div>
                                        <x-theme::form.label for="rating" text="Rating"/>
                                        <select id="rating" wire:model="rating" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                            @for($i = 5; $i >= 1; $i--)
                                                <option value="{{ $i }}">{{ $i }} star{{ $i === 1 ? '' : 's' }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div>
                                        <x-theme::form.label for="review_title" text="Title (optional)"/>
                                        <x-theme::form.input id="review_title" wire:model="review_title" placeholder="Short summary"/>
                                    </div>
                                    <div>
                                        <x-theme::form.label for="review_body" text="Review"/>
                                        <x-theme::form.textarea id="review_body" wire:model="review_body" rows="4" placeholder="What did you like or dislike?"/>
                                        @error('body') <x-theme::form.error :text="$message"/> @enderror
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <x-theme::button.primary type="submit">{{ $myReview ? 'Update review' : 'Submit review' }}</x-theme::button.primary>
                                        @if($myReview)
                                            <button type="button" wire:click="deleteReview" wire:confirm="Delete your review?" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Delete</button>
                                        @endif
                                    </div>
                                </form>
                            </x-theme::card>
                        @elseif(! $resource->isFree() && $resource->isListedPublicly())
                            <x-theme::alert.primary text="Purchase this resource to leave a review." />
                        @endif
                    @else
                        <x-theme::alert.primary text="Sign in to leave a review." />
                    @endauth

                    @forelse($reviews as $review)
                        <div wire:key="review-{{ $review->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $review->user?->getAvatarUrl() }}" alt="" class="h-9 w-9 rounded-full">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $review->user?->username }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $review->created_at?->diffForHumans() }}</div>
                                    </div>
                                </div>
                                <x-marketplace::star-rating :rating="$review->rating" />
                            </div>
                            @if($review->title)
                                <h3 class="mt-3 font-semibold text-gray-900 dark:text-white">{{ $review->title }}</h3>
                            @endif
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $review->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No reviews yet.</p>
                    @endforelse
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                @if($resource->author)
                    <x-marketplace::author-link :user="$resource->author" role="Author" />
                @endif

                @php
                    $collaborators = $resource->teamMembers
                        ->filter(fn ($member) => $member->user && (int) $member->user_id !== (int) $resource->user_id)
                        ->values();
                @endphp

                @if($collaborators->isNotEmpty())
                    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Collaborators</h3>
                        <ul class="space-y-3">
                            @foreach($collaborators as $member)
                                <li wire:key="collab-{{ $member->id }}">
                                    <x-marketplace::author-link :user="$member->user" :role="$member->role->label()" size="sm" />
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                    <div><dt class="text-gray-500 dark:text-gray-400">Views</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ number_format($resource->views_count) }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Downloads</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ number_format($resource->downloads_count) }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Purchases</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ number_format($resource->purchases_count) }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Links</h3>
                <ul class="space-y-2">
                    @if($resource->website_url)
                        <li><a href="{{ $resource->website_url }}" class="text-primary-700 hover:underline dark:text-primary-300" target="_blank" rel="noopener">Website</a></li>
                    @endif
                    @if($resource->docs_url)
                        <li><a href="{{ $resource->docs_url }}" class="text-primary-700 hover:underline dark:text-primary-300" target="_blank" rel="noopener">Documentation</a></li>
                    @endif
                    @if($resource->source_url)
                        <li><a href="{{ $resource->source_url }}" class="text-primary-700 hover:underline dark:text-primary-300" target="_blank" rel="noopener">Source</a></li>
                    @endif
                    @if($resource->support_url)
                        <li><a href="{{ $resource->support_url }}" class="text-primary-700 hover:underline dark:text-primary-300" target="_blank" rel="noopener">Support</a></li>
                    @endif
                    @if(! $resource->website_url && ! $resource->docs_url && ! $resource->source_url && ! $resource->support_url)
                        <li class="text-gray-500 dark:text-gray-400">No links provided.</li>
                    @endif
                </ul>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $resource->formattedPrice() }}</div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $resource->license_type }} license</p>
                @if($resource->reviews_count > 0)
                    <div class="mt-3">
                        <x-marketplace::star-rating :rating="$resource->reviews_avg" :count="$resource->reviews_count" />
                    </div>
                @endif

                @if($license)
                    <x-theme::alert.success class="mt-4" text="You have an active license." />
                    <p class="mt-2 break-all font-mono text-xs text-gray-600 dark:text-gray-300">{{ $license->license_key }}</p>
                @endif

                @if($latest?->integrated_marketplace_only)
                    <p class="mt-4 text-sm text-yellow-600 dark:text-yellow-400">This version can only be installed through the integrated marketplace.</p>
                @endif

                <div class="mt-4 space-y-2">
                    @if($latest && $latest->downloadableFromExtensionMarketplace($resource) && ($resource->isFree() || $license || $canManage))
                        <x-theme::button.primary href="{{ route('marketplace.versions.download', $latest) }}" class="block w-full text-center">
                            Download {{ $latest->version }}
                        </x-theme::button.primary>
                    @elseif(! $resource->isFree() && $resource->isListedPublicly())
                        @auth
                            @if($hasGateway)
                                <div class="space-y-2">
                                    @foreach($paymentMethods as $method)
                                        <x-theme::button.primary
                                            type="button"
                                            wire:click="buy({{ $method->id }})"
                                            class="w-full"
                                            wire:loading.attr="disabled"
                                            wire:target="buy({{ $method->id }})"
                                        >
                                            <span wire:loading.remove wire:target="buy({{ $method->id }})">
                                                {{ $paymentMethods->count() > 1 ? 'Pay with '.$method->name : 'Buy now' }}
                                            </span>
                                            <span wire:loading wire:target="buy({{ $method->id }})">Starting checkout…</span>
                                        </x-theme::button.primary>
                                    @endforeach
                                    @if($paymentMethods->count() > 1)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Choose any payment method offered by the author.</p>
                                    @endif
                                </div>
                            @else
                                <x-theme::alert.warning text="Purchases are unavailable until the creator configures a payment method." />
                            @endif
                        @else
                            <x-theme::button.primary href="{{ route('login') }}" class="block w-full text-center">Sign in to purchase</x-theme::button.primary>
                        @endauth
                    @endif

                    @if($canManage)
                        <a href="{{ $resource->studioUrl() }}" wire:navigate class="block w-full rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Manage resource</a>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</article>
