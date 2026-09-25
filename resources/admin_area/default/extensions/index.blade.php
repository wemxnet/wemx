@extends('admin::layouts.wrapper', [
    'activePage' => 'extensions',
])

@section('title', __('messages.extensions'))

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            <x-admin::button href="{{ route('admin.extensions.discover') }}"
                             wire:navigate>{{ __('messages.reload') }}</x-admin::button>
        </div>
    </div>
@endsection

@section('content')
    {{--  Extensions Table  --}}
    @livewire(admin_view_path('livewire.table'), [
        'title' => __('messages.extensions'),
        'entries' => 15,
        'columns' => [
            __('messages.identifier'),
            __('messages.name'),
            __('messages.description'),
            __('messages.type'),
            __('messages.version'),
            __('messages.status'),
            __('messages.created_at'),
            '',
        ],
        'sortableColumns' => [
            __('messages.identifier'),
            __('messages.name'),
            __('messages.type'),
            __('messages.version'),
            __('messages.status'),
            __('messages.created_at'),
        ],
        'rows' =>\App\Models\Extension::latest()->whereType('module')->get()->map(function ($extension) {
            return [
                $extension->identifier,
                $extension->extension()->getName(),
                $extension->extension()->getDescription(),
                $extension->type,
                $extension->extension()->getVersion(),
                '<span class="badge badge-outline text-' . ($extension->status == 'enabled' ? 'green' : 'red') . '">' . ucfirst($extension->status) . '</span>',
                $extension->created_at->translatedFormat('d M Y'),
                ($extension->status != 'enabled' ?
                    '<a href="' . route('admin.extensions.toggle', $extension->identifier) . '" class="text-info" wire:navigate>Enable</a>' :
                    '<a href="' . route('admin.extensions.toggle', $extension->identifier) . '" class="text-danger" wire:navigate>Disable</a>'
                )
            ];
        })->toArray(),
    ])
@endsection
