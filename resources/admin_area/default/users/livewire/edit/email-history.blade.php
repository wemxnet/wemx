<?php

use Livewire\Volt\Component;

new class extends Component
{
    public $user;

    public function mount($user)
    {
        $this->user = $user;
    }
}

?>

<div>
    <div class="d-flex justify-content-end mb-3">
        @perm('admin.users.update')
            <a href="{{ route('admin.users.send-email', $user->id) }}" class="btn btn-primary" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z" /><path d="M3 7l9 6l9 -6" /></svg>
                {{ __('messages.send_email') }}
            </a>
        @endperm
    </div>
    {{--  Email History Table  --}}
    @livewire(admin_view_path('livewire.table'), [
        'title' => __('messages.email_history'),
        'class' => '',
        'entries' => 15,
        'columns' => [
            __('messages.id'),
            __('messages.subject'),
            __('messages.to'),
            __('messages.status'),
            __('messages.created_at'),
            '',
        ],
        'sortableColumns' => [
            __('messages.id'),
            __('messages.user'),
            __('messages.subject'),
            __('messages.from'),
            __('messages.to'),
            __('messages.status'),
            __('messages.updated_at'),
            __('messages.created_at'),
        ],
        'rows' => $user->emails()->where('display', 1)->latest()->get()->map(function ($extension) {
            return [
                $extension->id,
                Str::limit($extension->subject, 50),
                $extension->to,
                $extension->status == 'delivered' ? '<span class="badge bg-green-lt">Delivered</span>' : ($extension->status == 'read' ? '<span class="badge bg-info-lt">Read</span>' : ($extension->status == 'failed' ? '<span class="badge bg-danger-lt">Failed</span>' : '<span class="badge bg-warning-lt">' . ucfirst($extension->status) . '</span>')),
                $extension->created_at->translatedFormat('d M Y H:i'),
                '<a href="' . route('admin.emails.view', $extension->id) . '" target="_blank">' . __('messages.view') . '</a>'
            ];
        })->toArray(),
    ])
</div>
