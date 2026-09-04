@extends('admin::layouts.wrapper', [
    'activePage' => 'email_templates',
])

@section('title', __('messages.edit_email_template'))

@section('content')
    @livewire(admin_view_path('emails.livewire.edit-template-form'), ['template' => $template])
@endsection
