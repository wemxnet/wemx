@extends('theme::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', $author->username)

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.marketplace.livewire.author-profile', ['username' => $author->username])
    </div>
@endsection
