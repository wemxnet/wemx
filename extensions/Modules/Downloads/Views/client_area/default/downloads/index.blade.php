@extends('theme::layouts.wrapper', [
    'activePage' => 'downloads',
])

@section('title', 'Downloads')

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Downloads</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Files and resources shared by our team.</p>
        </div>

        @if($folders->isEmpty())
            <x-theme::empty-state
                title="No downloads yet"
                description="When files are published, they will appear here."
            />
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($folders as $folder)
                    @php
                        $visibleFiles = $folder->filesVisibleTo(auth()->user());
                    @endphp
                    <a
                        href="{{ route('downloads.folder', $folder) }}"
                        wire:navigate
                        class="flex flex-col rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ $folder->name }}</h2>
                                @if($folder->description)
                                    <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ $folder->description }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                {{ $visibleFiles->count() }} {{ \Illuminate\Support\Str::plural('file', $visibleFiles->count()) }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
