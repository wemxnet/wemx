@extends('theme::layouts.wrapper', [
    'activePage' => 'categories',
])

@section('title', 'Categories')

@section('content')
    @php
        $isAdmin = auth()->check() && auth()->user()->isAdmin();
        $categories = \App\Models\Category::query()
            ->when($isAdmin, fn ($query) => $query->whereNotIn('status', ['disabled', 'unlisted']))
            ->when(! $isAdmin, fn ($query) => $query->where('status', 'active'))
            ->get();

        $requestedCategorySlug = request()->get('category');
        $requestedCategory = $requestedCategorySlug
            ? \App\Models\Category::where('slug', $requestedCategorySlug)->first()
            : null;
        $canViewRequestedCategory = ! $requestedCategory || match ($requestedCategory->status) {
            'active', 'unlisted' => true,
            'restricted' => $isAdmin,
            default => false,
        };
    @endphp

    <div class="mx-auto max-w-screen-xl px-0 sm:px-4 2xl:px-0">
        <div class="sm:flex sm:items-center sm:justify-between sm:gap-4">
            <h2 class="mt-3 text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">Categories</h2>
        </div>

        @if($categories->count() == 0)
            <x-theme::alert.warning text="No categories found." class="mt-6" />
        @endif

        <div class="mb-4 mt-6 grid grid-cols-1 gap-4 text-center sm:mt-8 sm:grid-cols-2 lg:mb-0 lg:grid-cols-4 xl:gap-8">
            @foreach($categories as $category)
            <a href="{{ route('categories.index', ['category' => $category->slug]) }}" wire:navigate class="grid place-content-center space-y-6 overflow-hidden rounded-lg border border-gray-200 p-6 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700 @if(request()->get('category') == $category->slug) bg-gray-100 dark:bg-gray-700 @else bg-white dark:bg-gray-800 @endif">
                <div class="flex items-center justify-center">
                    <img class="w-20" src="{{ $category->icon() }}" alt="Category Icon">
                </div>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $category->name }}
                </p>
            </a>
            @endforeach
        </div>

    </div>

    @if(request()->has('category') && $canViewRequestedCategory)
        @livewire(client_view_path('categories.livewire.product-list'), ['category' => request()->get('category')])
    @elseif(request()->has('category') && ! $canViewRequestedCategory)
        <div class="mx-auto max-w-screen-xl px-0 sm:px-4 2xl:px-0 mt-4">
            <x-theme::alert.warning text="This category is not available." />
        </div>
    @endif
@endsection
