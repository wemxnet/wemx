@extends('admin::layouts.wrapper', [
    'activePage' => 'email_templates',
])

@section('title', __('messages.email_templates'))

@section('content')
    <div class="alert alert-info m-0 mb-3">
        Edit the subject, body, and button label for emails sent to users. Event details such as order tables and links are filled in automatically.
    </div>

    @livewire(admin_view_path('livewire.table'), [
        'title' => __('messages.email_templates'),
        'entries' => 40,
        'columns' => [
            __('messages.email_template_group'),
            __('messages.title'),
            __('messages.subject'),
            __('messages.status'),
            '',
            '',
        ],
        'sortableColumns' => [
            __('messages.email_template_group'),
            __('messages.title'),
            __('messages.subject'),
            __('messages.status'),
        ],
        'rows' => collect(\App\Models\EmailTemplate::catalog())->map(function (array $template) {
            return [
                $template['group'],
                '<a href="'.route('admin.emails.templates.edit', $template['identifier']).'" wire:navigate>'.e($template['name']).'</a>',
                e(\Illuminate\Support\Str::limit($template['subject'], 60)),
                $template['enabled']
                    ? '<span class="badge bg-success-lt">'.e(__('messages.enabled')).'</span>'
                    : '<span class="badge bg-secondary-lt">'.e(__('messages.disabled')).'</span>',
                $template['customized']
                    ? '<span class="badge bg-blue-lt">'.e(__('messages.email_template_customized')).'</span>'
                    : '<span class="badge bg-secondary-lt">'.e(__('messages.email_template_default')).'</span>',
                '<a href="'.route('admin.emails.templates.edit', $template['identifier']).'" wire:navigate>'.e(__('messages.edit')).'</a>',
            ];
        })->toArray(),
    ])
@endsection
