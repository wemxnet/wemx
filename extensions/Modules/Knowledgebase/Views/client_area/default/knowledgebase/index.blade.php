@extends('theme::layouts.wrapper', [
    'activePage' => 'knowledgebase',
])

@section('title', __('knowledgebase::messages.knowledgebase'))

@section('content')
    <div class="mx-auto max-w-screen-xl px-2 sm:px-4">
        @livewire('client_area.default.knowledgebase.livewire.browse')
    </div>
@endsection
