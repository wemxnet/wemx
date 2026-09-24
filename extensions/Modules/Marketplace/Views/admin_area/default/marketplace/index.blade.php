@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace-manager',
])

@section('title', 'Marketplace')

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            @perm('admin.marketplace.manage')
                <x-admin::button href="{{ route('admin.marketplace-manager.resources.index') }}" wire:navigate>Manage resources</x-admin::button>
            @endperm
        </div>
    </div>
@endsection

@section('content')
    @livewire('admin_area.default.marketplace.livewire.manage-overview')
@endsection
