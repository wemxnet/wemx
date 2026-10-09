<?php

use Livewire\Volt\Component;
use App\Models\Gateway;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

new class extends Component
{
    public $gatewayConfig;

    public $display_name;

    public $display_description;

    public $icon;

    public bool $is_enabled;

    public bool $is_staff_only = false;

    public array $config = [];

    /** Keys of password fields that already have a value saved. The values themselves never reach the browser. */
    public array $savedSecrets = [];

    /** @return array<int, string> */
    protected function secretKeys(): array
    {
        return collect($this->gatewayConfig->gateway->getConfig())
            ->filter(fn ($field) => ($field['type'] ?? 'text') === 'password')
            ->keys()
            ->all();
    }

    public function mount($gatewayConfig)
    {
        $this->display_name = $gatewayConfig->display_name;
        $this->display_description = $gatewayConfig->display_description;
        $this->icon = $gatewayConfig->icon;

        // Secrets (API keys, webhook secrets…) stay on the server: the form only knows whether one is saved.
        $config = $gatewayConfig->config ?? [];
        foreach ($this->secretKeys() as $key) {
            if (filled($config[$key] ?? null)) {
                $this->savedSecrets[] = $key;
            }
            unset($config[$key]);
        }
        $this->config = $config;
        $this->is_enabled = (bool) $gatewayConfig->is_active;
        $this->is_staff_only = (bool) $gatewayConfig->is_staff_only;
    }

    public function updateConfig()
    {
        // A password field left blank keeps the value that's already saved.
        $config = $this->config;
        $stored = $this->gatewayConfig->fresh()->config ?? [];
        foreach ($this->secretKeys() as $key) {
            if (blank($config[$key] ?? null) && filled($stored[$key] ?? null)) {
                $config[$key] = $stored[$key];
            }
        }

        \App\Models\GatewayConfig::actions()->updateGatewayConfigAsAdmin([
            'gateway_id' => $this->gatewayConfig->id,
            'display_name' => $this->display_name,
            'display_description' => $this->display_description,
            'icon' => $this->icon,
            'config' => $config,
            'is_enabled' => $this->is_enabled,
            'is_staff_only' => $this->is_staff_only,
        ]);
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Update Gateway Config</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="display_name">{{ __('messages.display_name') }}</label>
            <div class="col">
                <input type="text" wire:model="display_name" class="form-control" aria-describedby="display_name" id="display_name" placeholder="Display Name" />
                @error('display_name')
                    <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">
                        The name that will be displayed to users
                    </small>
                @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="display_description-input">Display Description</label>
            <div class="col">
                <textarea class="form-control @error('display_description') is-invalid @enderror" wire:model="display_description" id="display_description-input" rows="2" placeholder="Content.."></textarea>
                @error('display_description')
                    <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">Enter the description as displayed to customers</small>
                @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="icon-input">{{ __('messages.icon') }} (Optional)</label>
            <div class="col">
                <div>
                    @if($icon)
                        <img src="{{ $icon }}" class="avatar avatar-xl mb-3" alt="category icon">
                    @endif

                    <div>
                        <input type="text" wire:model.change="icon" class="form-control mb-1" aria-describedby="icon_url-input" id="icon_url-input" placeholder="{{ __('messages.icon_url') }}">
                        @error('icon')
                            <x-admin::form.error :message="$message" />
                        @else
                            <small class="form-hint mb-3">
                                The icon URL for this gateway as displayed to customers
                            </small>
                        @enderror
                            <x-admin::button href="{{ route('admin.images.index') }}" target="_blank" class="mb-2">
                                <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-file-upload"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M12 11v6" /><path d="M9.5 13.5l2.5 -2.5l2.5 2.5" /></svg>
                                Upload Images
                            </x-admin::button>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="is_enabled">{{ __('messages.enabled') }}</label>
            <div class="col">
                <label class="form-check form-switch">
                    <input class="form-check-input" wire:model="is_enabled" type="checkbox" />
                    <span class="form-check-label">Is this gateway enabled?</span>
                </label>
                @error('is_enabled')
                    <x-admin::form.error :message="$message" />
                @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="is_staff_only">Staff Only</label>
            <div class="col">
                <label class="form-check form-switch">
                    <input class="form-check-input" wire:model="is_staff_only" type="checkbox"/>
                    <span class="form-check-label">
                            Only staff members with the permission 'use-staff-gateways' can use this gateway at checkout.
                        </span>
                </label>
                @error('is_staff_only')
                    <x-admin::form.error :message="$message"/>
                @enderror
            </div>
        </div>
        <div class="hr"></div>
        @foreach($gatewayConfig->gateway->getConfig() as $key => $config)
            @if($config['type'] === 'select')
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="config-{{ $key }}-input">{{ $config['label'] }}</label>
                <div class="col">
                    <select class="form-select @error($key) is-invalid @enderror" wire:model="config.{{ $key }}" id="config-{{ $key }}-input">
                        <option value="">{{ __('messages.select') }}</option>
                        @foreach($config['options'] ?? [] as $optionKey => $option)
                            <option value="{{ $optionKey }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @error($key)
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">{{ $config['description'] ?? '' }}</small>
                    @enderror
                </div>
            </div>
            @else
            <div class="mb-3 row">
                <label class="col-3 col-form-label required" for="config-{{ $key }}-input">{{ $config['label'] }}</label>
                <div class="col">
                    @php($isSavedSecret = ($config['type'] ?? 'text') === 'password' && in_array($key, $savedSecrets, true))
                    <input type="{{ $config['type'] ?? 'text' }}" value="{{ $config['default_value'] ?? '' }}" wire:model="config.{{ $key }}" class="form-control @error($key) is-invalid @enderror" aria-describedby="config-{{ $key }}-input" id="config-{{ $key }}-input" placeholder="{{ $isSavedSecret ? '•••••••• Saved. Leave blank to keep it, or enter a new value' : ($config['placeholder'] ?? $config['label']) }}" @if(($config['type'] ?? 'text') === 'password') autocomplete="new-password" @endif/>
                    @error($key)
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">{{ $config['description'] ?? '' }}</small>
                    @enderror
                </div>
            </div>
            @endif
        @endforeach
    </div>
    <div class="card-footer text-end">
        <button type="button" wire:click="updateConfig" class="btn btn-primary">{{ __('messages.update') }}</button>
    </div>
</div>

