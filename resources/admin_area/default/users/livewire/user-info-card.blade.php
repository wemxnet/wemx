<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component
{
    public $user;

    public function mount(User $user)
    {
        $this->user = $user;
    }

    #[\Livewire\Attributes\On('user-updated')]
    public function userUpdated()
    {

    }
}

?>

<div class="card">
    <div class="card-header">
        <div>
            <div class="row align-items-center">
                <div class="col-auto">
                    <span class="avatar" style="background-image: url('{{ $user->getAvatarUrl() }}');"></span>
                </div>
                <div class="col">
                    <div class="card-title"><a href="{{ route('admin.users.edit', $user->id) }}" class="text-reset" wire:navigate>{{ $user->fullname }}</a></div>
                    <div class="card-subtitle"><a href="{{ route('admin.users.edit', $user->id) }}" class="text-reset" wire:navigate>{{ $user->username }} ({{ $user->email }})</a></div>
                </div>
            </div>
        </div>
        <div class="card-actions btn-list">
            @perm('admin.users.update')
            <a href="{{ route('admin.users.send-email', $user->id) }}" class="btn" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10z" /><path d="M3 7l9 6l9 -6" /></svg>
                {{ __('messages.send_email') }}
            </a>
            @endperm
            @perm('admin.users.impersonate')
            <a href="{{ route('admin.users.impersonate', $user->id) }}" class="btn btn-primary">
                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M21 12h-13l3 -3" /><path d="M11 15l-3 -3" /></svg>
                {{ __('messages.login_as_user') }}
            </a>
            @endperm
        </div>
    </div>
    <div class="card-body">
        <div class="datagrid">
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.full_name') }}</div>
                <div class="datagrid-content">{{ $user->fullname }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.email') }}</div>
                <div class="datagrid-content">{{ $user->email }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.username') }}</div>
                <div class="datagrid-content">{{ $user->username }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.balance') }}</div>
                <div class="datagrid-content">
                    {{ priceIn($user->balance, baseCurrency()) }}
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.phone') }}</div>
                <div class="datagrid-content">{{ $user->phone ? : '-' }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.country') }}</div>
                <div class="datagrid-content d-flex align-items-center">
                    <span class="flag flag-xxs flag-country-{{ strtolower($user->address->country ?? 'aq') }} me-1"></span> {{ $user->address->country_name ? : "Unkown" }}
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.language') }}</div>
                <div class="datagrid-content d-flex align-items-center">
                    <span class="flag flag-xxs flag-country-{{ $user->language()->flag }} me-1"></span>
                    {{ $user->language()->name }} ({{ strtoupper($user->language) }})
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Active Orders</div>
                <div class="datagrid-content">
                    {{ $user->orders()->where('status', 'active')->count() }} {{ __('messages.orders') }}
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.created_at') }}</div>
                <div class="datagrid-content">
                    {{ $user->created_at->diffForHumans() }}
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.updated_at') }}</div>
                <div class="datagrid-content">
                    {{ $user->updated_at->diffForHumans() }}
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">{{ __('messages.last_seen_at') }}</div>
                <div class="datagrid-content">
                    {{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : "Never" }}
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Email Verified At</div>
                <div class="datagrid-content">
                    {{ $user->email_verified_at ? $user->email_verified_at->diffForHumans() : "Never" }}
                </div>
            </div>
        </div>
    </div>
</div>



