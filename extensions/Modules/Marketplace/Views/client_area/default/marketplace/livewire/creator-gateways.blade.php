<?php

use Extensions\Modules\Marketplace\Gateways\CreatorGatewayRegistry;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $driver = 'stripe';

    public array $credentials = [];

    public function mount(): void
    {
        $this->resetCredentialFields();
    }

    #[Computed]
    public function configs()
    {
        return MarketplaceCreatorGatewayConfig::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    public function updatedDriver(): void
    {
        $this->resetCredentialFields();
    }

    public function resetCredentialFields(): void
    {
        $this->credentials = collect(CreatorGatewayRegistry::make($this->driver)->credentialFields())
            ->mapWithKeys(fn ($field, $key) => [$key => $field['type'] === 'select' ? array_key_first($field['options'] ?? []) : ''])
            ->all();
    }

    public function save(): void
    {
        MarketplaceCreatorGatewayConfig::actions()->create([
            'user_id' => auth()->id(),
            'name' => $this->name,
            'driver' => $this->driver,
            'credentials' => $this->credentials,
        ]);

        $this->name = '';
        $this->resetCredentialFields();
        unset($this->configs);
        session()->flash('success', 'Payment method saved.');
    }

    public function toggle(int $id): void
    {
        MarketplaceCreatorGatewayConfig::actions()->update([
            'user_id' => auth()->id(),
            'gateway_config_id' => $id,
            'is_enabled' => ! MarketplaceCreatorGatewayConfig::findOrFail($id)->is_enabled,
        ]);

        unset($this->configs);
    }

    public function delete(int $id): void
    {
        MarketplaceCreatorGatewayConfig::actions()->delete([
            'user_id' => auth()->id(),
            'gateway_config_id' => $id,
        ]);

        unset($this->configs);
    }
}

?>

@php
    $fields = CreatorGatewayRegistry::make($this->driver)->credentialFields();
@endphp

<div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div>
        <h1 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">Payment methods</h1>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">These methods are separate from WemX billing gateways. Attach one to each paid resource.</p>

        @if(session('success'))
            <x-theme::alert.success :text="session('success')" />
        @endif

        @if($this->configs->isEmpty())
            <x-theme::empty-state title="No payment methods yet" description="Add Stripe or PayPal IPN to sell paid resources." />
        @else
            <div class="space-y-3">
                @foreach($this->configs as $config)
                    <div wire:key="gw-{{ $config->id }}" class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $config->name }}</div>
                            <div class="text-xs text-gray-500">{{ $config->driverName() }} · {{ $config->is_enabled ? 'Enabled' : 'Disabled' }}</div>
                        </div>
                        <div class="flex gap-3 text-sm">
                            <button type="button" class="text-primary-700 hover:underline" wire:click="toggle({{ $config->id }})">{{ $config->is_enabled ? 'Disable' : 'Enable' }}</button>
                            <button type="button" class="text-red-600 hover:underline" wire:click="delete({{ $config->id }})" wire:confirm="Delete this payment method?">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add a method</h2>
        <div>
            <x-theme::form.label for="name" text="Display name"/>
            <x-theme::form.input id="name" wire:model="name" placeholder="My Stripe account"/>
            @error('name') <x-theme::form.error :text="$message"/> @enderror
        </div>
        <div>
            <x-theme::form.label for="driver" text="Gateway type"/>
            <select id="driver" wire:model.live="driver" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                @foreach(CreatorGatewayRegistry::options() as $option)
                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                @endforeach
            </select>
            <x-theme::form.description :text="CreatorGatewayRegistry::make($this->driver)->description()"/>
        </div>
        @foreach($fields as $key => $field)
            <div wire:key="cred-{{ $driver }}-{{ $key }}">
                <x-theme::form.label :for="'cred-'.$key" :text="$field['label']"/>
                @if(($field['type'] ?? 'text') === 'select')
                    <select id="cred-{{ $key }}" wire:model="credentials.{{ $key }}" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($field['options'] ?? [] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <x-theme::form.input id="cred-{{ $key }}" :type="$field['type'] === 'password' ? 'password' : ($field['type'] ?? 'text')" wire:model="credentials.{{ $key }}"/>
                @endif
                @if(! empty($field['description']))
                    <x-theme::form.description :text="$field['description']"/>
                @endif
                @error("credentials.$key") <x-theme::form.error :text="$message"/> @enderror
            </div>
        @endforeach
        <x-theme::button.primary type="submit">Save method</x-theme::button.primary>
    </form>
</div>
