@extends('theme::layouts.wrapper', [
    'activePage' => 'page-'.$page->slug,
])

@section('title', $page->title)

@section('content')
    <div class="mx-auto max-w-screen-xl px-0 sm:px-4 2xl:px-0">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2 class="pb-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $page->title }}</h2>
                    </div>
                    <div class="card-body">
                        <div class="format format-blue dark:format-invert max-w-none">
                            {!! \Illuminate\Support\Str::markdown($page->content) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
