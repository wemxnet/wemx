@extends('theme::layouts.wrapper', [
    'activePage' => 'downloads',
])

@section('title', $folder->name)

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        <div class="mb-6">
            <a href="{{ route('downloads.index') }}" wire:navigate class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-400">← All downloads</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $folder->name }}</h1>
            @if($folder->description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $folder->description }}</p>
            @endif
        </div>

        @if($files->isEmpty())
            <x-theme::empty-state
                title="No files in this folder"
                description="Check back later or browse another folder."
                action-text="All downloads"
                :action-href="route('downloads.index')"
                :action-navigate="true"
            />
        @else
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($files as $file)
                        @php
                            $canDownload = $file->canBeDownloadedBy(auth()->user(), request()->ip());
                            $denial = $file->denialLabel(auth()->user(), request()->ip());
                        @endphp
                        <li class="p-4 sm:p-5" wire:key="download-file-{{ $file->id }}">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $file->name }}</h2>
                                        @if($file->version)
                                            <span class="rounded-sm bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">v{{ $file->version }}</span>
                                        @endif
                                        <span class="text-xs text-gray-400">{{ $file->formattedSize() }}</span>
                                    </div>
                                    @if($file->description)
                                        <div class="prose prose-sm mt-2 max-w-none text-gray-600 dark:prose-invert dark:text-gray-300">
                                            {!! $file->renderedDescription() !!}
                                        </div>
                                    @endif
                                </div>
                                <div class="shrink-0">
                                    @if($canDownload)
                                        <a
                                            href="{{ route('downloads.download', [$folder, $file]) }}"
                                            class="inline-flex items-center justify-center rounded-lg bg-primary-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700"
                                        >
                                            Download
                                        </a>
                                    @elseif(! auth()->check() && $file->denialReason(null, request()->ip()) === \Extensions\Modules\Downloads\Models\DownloadFile::DENIAL_LOGIN)
                                        <a
                                            href="{{ route('login') }}"
                                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Sign in to download
                                        </a>
                                    @else
                                        <div class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                            {{ $denial }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
