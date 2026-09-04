@extends('admin::layouts.wrapper', [
    'activePage' => 'users'
])

@section('title', __('messages.send_email_to', ['name' => $user->full_name]))

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn" wire:navigate>
                {{ __('messages.back') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
    @livewire(admin_view_path('users.livewire.send-user-email'), ['user' => $user])
@endsection
