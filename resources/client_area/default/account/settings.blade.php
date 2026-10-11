@extends('theme::layouts.wrapper', [
    'activePage' => 'account-settings',
])

@section('title', 'Account Settings')

@section('content')
    <div class="mx-auto max-w-screen-2xl px-0 sm:px-4 2xl:px-0">
        @livewire(client_view_path('account.livewire.account-settings'))
    </div>
@endsection
