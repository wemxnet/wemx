@extends('admin::layouts.wrapper', [
    'activePage' => 'mass_mails',
])

@section('title', $massMail->subject)

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
    @livewire(admin_view_path('emails.livewire.mass-mail-show'), ['massMail' => $massMail])
@endsection
