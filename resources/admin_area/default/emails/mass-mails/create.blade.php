@extends('admin::layouts.wrapper', [
    'activePage' => 'mass_mails',
])

@section('title', __('messages.mass_mail_compose'))

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            <a href="{{ route('admin.emails.mass-mails.index') }}" class="btn" wire:navigate>
                {{ __('messages.back') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="alert alert-info m-0 mb-3">
        {{ __('messages.mass_mail_intro') }}
    </div>

    @livewire(admin_view_path('emails.livewire.mass-mail-form'))
@endsection
