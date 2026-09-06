@extends('admin::layouts.wrapper', [
    'activePage' => 'mass_mails',
])

@section('title', __('messages.mass_mails'))

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            <a href="{{ route('admin.emails.mass-mails.create') }}" class="btn btn-primary" wire:navigate>
                {{ __('messages.mass_mail_compose') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
    @livewire(admin_view_path('livewire.table'), [
        'title' => __('messages.mass_mails'),
        'entries' => 15,
        'columns' => [
            __('messages.id'),
            __('messages.subject'),
            __('messages.mass_mail_audience'),
            __('messages.mass_mail_recipients'),
            __('messages.status'),
            __('messages.mass_mail_scheduled_at'),
            '',
        ],
        'sortableColumns' => [
            __('messages.id'),
            __('messages.subject'),
            __('messages.mass_mail_audience'),
            __('messages.mass_mail_recipients'),
            __('messages.status'),
            __('messages.mass_mail_scheduled_at'),
        ],
        'rows' => \App\Models\MassMail::query()->latest()->get()->map(function (\App\Models\MassMail $mail) {
            $statusClass = match ($mail->status) {
                \App\Models\MassMail::STATUS_SENT => 'bg-green-lt',
                \App\Models\MassMail::STATUS_SENDING => 'bg-yellow-lt',
                \App\Models\MassMail::STATUS_QUEUED => 'bg-blue-lt',
                \App\Models\MassMail::STATUS_FAILED => 'bg-danger-lt',
                default => 'bg-secondary-lt',
            };

            return [
                $mail->id,
                '<a href="'.route('admin.emails.mass-mails.show', $mail).'" wire:navigate>'.e(\Illuminate\Support\Str::limit($mail->subject, 50)).'</a>',
                e($mail->audienceLabel()),
                $mail->sent_count.'/'.$mail->recipient_count,
                '<span class="badge '.$statusClass.'">'.e(ucfirst($mail->status)).'</span>',
                $mail->scheduled_at?->translatedFormat('d M Y H:i') ?? '—',
                '<a href="'.route('admin.emails.mass-mails.show', $mail).'" wire:navigate>'.e(__('messages.view')).'</a>',
            ];
        })->toArray(),
    ])
@endsection
