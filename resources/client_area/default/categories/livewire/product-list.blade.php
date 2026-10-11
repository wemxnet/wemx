<?php

use Livewire\Volt\Component;
use Illuminate\View\View;
use App\Models\Category;
use App\Models\Package;

new class extends Component
{
    public $category;

    public $packages;

    public function mount($category)
    {
        $category = Category::whereSlug($category)->firstOrFail();
        $isAdmin = auth()->check() && auth()->user()->isAdmin();
        $canViewCategory = match ($category->status) {
            'active', 'unlisted' => true,
            'restricted' => $isAdmin,
            default => false,
        };

        abort_unless($canViewCategory, 404);

        $this->category = $category;
        $this->packages = Package::query()
            ->where('category_id', $category->id)
            ->visibleToUser(auth()->user(), includeUnlisted: false)
            ->with(['prices', 'features'])
            ->get();
    }
}

?>

<section class="bg-gray-50 py-4 antialiased dark:bg-gray-900 md:py-4">
    <div class="mx-auto max-w-screen-xl px-0 sm:px-4 2xl:px-0">
        <!-- Heading & Filters -->
        <div class="mb-4 items-end justify-between space-y-4 sm:flex sm:space-y-0 md:mb-8">
            <div>
                <h2 class="mt-3 text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">{{ $category->name }}</h2>
                <p class="text-gray-500 sm:text-xl dark:text-gray-400">{{ $category->description }}</p>
            </div>
        </div>

        <div class="grid gap-8 mb-8 grid-cols-6">
            @foreach($packages as $package)
            <!-- Pricing Card -->
            <div class="flex flex-col p-6 col-span-6 md:col-span-3 lg:col-span-2 mx-auto max-w-lg text-center text-gray-900 bg-white rounded-lg border border-gray-200 shadow-sm dark:border-gray-700 xl:p-8 dark:text-white dark:bg-gray-800">
                <h3 class="mb-4 text-2xl font-semibold">{{ $package->name }}</h3>
                <p class="text-gray-500 text-light sm:text-lg dark:text-gray-400">{{ Str::limit($package->short_description, 70) }}</p>
                <div class="my-8 flex flex-wrap items-baseline justify-center gap-x-2">
                    <span class="text-4xl font-extrabold sm:text-5xl">{{ price($package->prices->first()->price) }}</span>
                    <span class="text-gray-500">/{{ $package->prices->first()->cycle() }}</span>
                </div>
                <!-- List -->
                <ul role="list" class="mb-8 space-y-4 text-left">
                    @foreach($package->features as $feature)
                    <li class="flex items-center space-x-3">
                        <!-- Icon -->
                        <svg class="flex-shrink-0 w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                        <span>{{ $feature->description }}</span>
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('packages.view', $package->slug) }}" wire:navigate class="text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:ring-primary-200 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:focus:ring-primary-900">View Package</a>
            </div>
            @endforeach
        </div>

    </div>
</section>
