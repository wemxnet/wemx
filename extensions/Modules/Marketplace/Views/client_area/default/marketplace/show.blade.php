@extends('theme::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', $resource->name)

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.marketplace.livewire.resource-show', ['resourceId' => $resource->id])
    </div>
@endsection
