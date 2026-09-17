<?php

use Livewire\Volt\Component;
use Illuminate\View\View;

use App\Models\ServerConnection;
use Livewire\Attributes\Computed;

new class extends Component
{
    public $alias;

    public $description;

    public $connectionId;

    public array $config = [];

    public $connectionError = '';

    public $connectionSuccessful = false;

    public $connectionFailCount = 0;

    public $connectionAlerts = false;

    public $preventPurchasing = false;

    public $connectionAlertsEmail = '';

    public function mount($connection)
    {
        $this->connectionId = $connection->id;
        $this->alias = $connection->alias;
        $this->description = $connection->short_description;
        $this->config = is_array($connection->config) ? $connection->config : [];
        $this->connectionAlerts = $connection->receive_alerts;
        $this->preventPurchasing = $connection->prevent_purchasing;
        $this->connectionAlertsEmail = $connection->alert_email;
    }

    #[Computed]
    public function connection()
    {
        return ServerConnection::find($this->connectionId);
    }

    #[Computed]
    public function server()
    {
        return $this->connection->server;
    }

    public function validateConfigInput()
    {
        $rules = array_merge($this->server->getConfigRules(
            prefix: 'config.',
        ), [
            'alias' => 'required|string|unique:server_connections,alias,' . $this->connectionId,
            'description' => 'nullable|string',
            'connectionAlertsEmail' => 'nullable|email',
        ]);

        $this->validate($rules);
    }

    public function testConnection()
    {
        $this->validateConfigInput();

        try {
            $this->server->testConnection($this->config);
        } catch (\Exception $e) {
            $this->connectionFailCount++;
            $this->connectionSuccessful = false;
            $this->connectionError = $e->getMessage();
            return;
        }

        $this->connectionFailCount = 0;
        $this->connectionSuccessful = true;
        $this->connectionError = '';
    }

    public function skipTestConnection()
    {
        $this->validateConfigInput();
        $this->connectionError = 'Skipping connection test.';
        $this->connectionSuccessful = true;
    }

    public function updateConnection()
    {
        $this->validateConfigInput();

        ServerConnection::actions()->updateServerConnectionAsAdmin([
            'connection_id' => $this->connectionId,
            'alias' => $this->alias,
            'short_description' => $this->description,
            'config' => $this->config,
            'status' => 'healthy',
            'receive_alerts' => $this->connectionAlerts,
            'alert_email' => $this->connectionAlertsEmail ?? settings('admin_email', auth()->user()->email),
            'prevent_purchasing' => $this->preventPurchasing,
        ]);

        $this->redirect(route('admin.servers.connections.edit', $this->connectionId), true);
    }

    public function deleteConnection()
    {
        $this->resetErrorBag();

        ServerConnection::actions()->deleteServerConnectionAsAdmin([
            'connection_id' => $this->connectionId,
        ]);

        return redirect()
            ->route('admin.servers.connections')
            ->with('success', 'Server connection deleted successfully');
    }

    public function rendering(View $view)
    {
        if(!$this->connectionAlertsEmail) {
            $this->connectionAlertsEmail = settings('admin_email', auth()->user()->email);
        }
    }

    // when server has updated, reset connection test
    public function updatedServerId()
    {
        // set default config
        $this->config = [];

        if($this->server ?? null) {
            foreach($this->server->getConfig() as $config) {
                $this->config[$config['key']] = $config['default_value'] ?? null;
            }
        }
    }
}

?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('messages.edit_server_connection') }}</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="server-input">{{ __('messages.select_server') }}</label>
            <div class="col">
                <select class="form-select" id="server-input" disabled>
                        <option>{{ $this->connection->server->extension()->getName() }}</option>
                </select>
                <small class="form-hint">{{ __('messages.select_server_desc') }}</small>
            </div>
        </div>
        @if($this->server ?? null)
            <div class="mb-3 row">
                <label class="col-3 col-form-label required" for="alias-input">{{ __('messages.alias') }}</label>
                <div class="col">
                    <input type="text" wire:model="alias" class="form-control @error('alias') is-invalid @enderror" aria-describedby="alias-input" id="alias-input" placeholder="{{ __('messages.alias') }}">
                    @error('alias')
                    <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">{{ __('messages.server_alias_desc') }}</small>
                    @enderror
                </div>
            </div>
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="description-input">{{ __('messages.description') }}</label>
                <div class="col">
                    <textarea class="form-control @error('description') is-invalid @enderror" wire:model="description" id="description-input" rows="2" placeholder="Content.."></textarea>
                    @error('description')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">{{ __('messages.server_description_desc') }}</small>
                    @enderror
                </div>
            </div>
                @foreach($this->server->getConfig() as $config)
                    <div class="mb-3 row">
                        <label class="col-3 col-form-label required" for="config-{{ $config['key'] }}-input">{{ $config['name'] }}</label>
                        <div class="col">
                            @if($config['type'] == 'select')
                                <x-admin::form.select wire:model="config.{{ $config['key'] }}" id="config-{{ $config['key'] }}-input" :options="$config['options']" :placeholder="$config['name']" />
                            @else
                                <input type="{{ $config['type'] ?? 'text' }}" wire:model="config.{{ $config['key'] }}" class="form-control" aria-describedby="config-{{ $config['key'] }}-input" id="config-{{ $config['key'] }}-input" placeholder="{{ $config['name'] }}">
                            @endif

                            @error('config.'. $config['key'])
                            <x-admin::form.error :message="$message" />
                            @else
                                <small class="form-hint">{{ $config['description'] ?? '' }}</small>
                                @enderror
                        </div>
                    </div>
                @endforeach
                @if($this->server->hasTestConnection())
                <div class="mb-3 row">
                    <label class="col-3 col-form-label required" for="testconnection-input">{{ __('messages.test_connection') }}</label>
                    <div class="col">
                        <x-admin::button wire:click="testConnection()" onclick="isLoading(this)" class="mb-2" id="testConnectionBtn">
                            <x-admin::icon icon="plug-connected" />
                            {{ __('messages.test_connection') }}
                        </x-admin::button>
                        @if($connectionFailCount > 3)
                            <x-admin::button color="warning" label="Skip Test Connection" wire:confirm="Are you sure you want to skip testing the connection? Skipping may lead to bugs or issues." wire:click="skipTestConnection()" class="mb-2 ml-2"/>
                        @endif
                        @if($connectionError)
                        <div class="alert alert-important alert-danger alert-dismissible mt-2" role="alert">
                            <div class="d-flex">
                                <div>
                                    {{ $connectionError }}
                                </div>
                            </div>
                        </div>
                        @endif
                        @if($connectionSuccessful)
                            <div class="alert alert-success mt-2">
                                Successfully made a connection to the server.
                            </div>
                        @else
                            <small class="form-hint">Test the connection to the server</small>
                        @endif
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-3 col-form-label" for="testconnection-input">Connection Alerts</label>
                    <div class="col">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" wire:model.live="connectionAlerts">
                        </label>
                        <small class="form-hint">
                            Do you want to receive email alerts when the connection is down?
                        </small>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label class="col-3 col-form-label" for="testconnection-input">Prevent Purchasing</label>
                    <div class="col">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" wire:model.live="preventPurchasing">
                        </label>
                        <small class="form-hint">
                            Prevent users from purchasing products/services that use this server connection when the connection is down.
                        </small>
                    </div>
                </div>
                @if($connectionAlerts)
                <div class="mb-3 row">
                    <label class="col-3 col-form-label" for="testconnection-input">{{ __('messages.email') }}</label>
                    <div class="col">
                        <input type="text" wire:model="connectionAlertsEmail" class="form-control @error('connectionAlertsEmail') is-invalid @enderror" aria-describedby="alias-input" id="alias-input" placeholder="{{ __('messages.email') }}">
                        @error('connectionAlertsEmail')
                        <x-admin::form.error :message="$message" />
                        @else
                            <small class="form-hint">We'll send an email to this address when the connection is down.</small>
                        @enderror
                    </div>
                </div>
                @endif
                @endif
        @endif
    </div>
    <div class="card-footer text-end">
        <button
            type="button"
            class="btn btn-danger me-2"
            wire:click="deleteConnection"
            wire:confirm.prompt="Are you sure you want to delete this server connection? This cannot be undone. Enter the Connection ID to confirm|{{ $connectionId }}"
        >
            Delete Connection
        </button>
        <button type="button" wire:click="updateConnection" class="btn btn-primary" @if($this->server ?? null AND $this->server->hasTestConnection() AND !$connectionSuccessful) disabled data-bs-toggle="tooltip" data-bs-placement="top" title="Tooltip on top" @endif>{{ __('messages.update') }}</button>
        @error('connection_id')
            <x-admin::form.error :message="$message" />
        @enderror
    </div>

    <script>
        document.addEventListener('livewire:initialized', function () {
            document.getElementById('testConnectionBtn').click();
        });
    </script>
</div>
