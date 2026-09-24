<?php

namespace Extensions\Modules\Marketplace\Actions;

use App\Actions\Action;
use Extensions\Modules\Marketplace\Actions\Concerns\AuthorizesMarketplaceStaff;
use Extensions\Modules\Marketplace\Gateways\CreatorGatewayRegistry;
use Extensions\Modules\Marketplace\Models\MarketplaceCreatorGatewayConfig;
use Extensions\Modules\Marketplace\Support\MarketplaceLimits;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MarketplaceCreatorGatewayActions extends Action
{
    use AuthorizesMarketplaceStaff;

    public function create(array $input): MarketplaceCreatorGatewayConfig
    {
        $validated = Validator::make($input, $this->rules())->validate();

        $user = $this->user((int) $validated['user_id']);
        MarketplaceLimits::assertCanCreateCreatorGateway($user);
        $gateway = CreatorGatewayRegistry::make($validated['driver']);
        $credentials = $gateway->validateCredentials($validated['credentials'] ?? []);

        return MarketplaceCreatorGatewayConfig::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'credentials' => $credentials,
            'settings' => $validated['settings'] ?? [],
            'is_enabled' => $validated['is_enabled'] ?? true,
        ]);
    }

    public function update(array $input): MarketplaceCreatorGatewayConfig
    {
        $validated = Validator::make($input, array_merge($this->rules(updating: true), [
            'gateway_config_id' => ['required', 'integer', 'exists:marketplace_creator_gateway_configs,id'],
        ]))->validate();

        $user = $this->user((int) $validated['user_id']);
        $config = MarketplaceCreatorGatewayConfig::findOrFail($validated['gateway_config_id']);

        if ((int) $config->user_id !== (int) $user->id && ! $user->isStaff()) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'You do not own this payment method.',
            ]);
        }

        $payload = [];

        if (isset($validated['name'])) {
            $payload['name'] = $validated['name'];
        }

        if (array_key_exists('is_enabled', $validated)) {
            $payload['is_enabled'] = $validated['is_enabled'];
        }

        if (isset($validated['credentials'])) {
            $incoming = array_filter(
                $validated['credentials'],
                fn ($value) => $value !== null && $value !== '',
            );

            $merged = array_merge($config->credentials ?? [], $incoming);
            $payload['credentials'] = $config->driver()->validateCredentials($merged);
        }

        $config->update($payload);

        return $config->fresh();
    }

    public function delete(array $input): bool
    {
        $validated = Validator::make($input, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'gateway_config_id' => ['required', 'integer', 'exists:marketplace_creator_gateway_configs,id'],
        ])->validate();

        $user = $this->user((int) $validated['user_id']);
        $config = MarketplaceCreatorGatewayConfig::findOrFail($validated['gateway_config_id']);

        if ((int) $config->user_id !== (int) $user->id && ! $user->isStaff()) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'You do not own this payment method.',
            ]);
        }

        if ($config->resources()->exists() || $config->legacyResources()->exists()) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'Detach this payment method from resources before deleting it.',
            ]);
        }

        return (bool) $config->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => [$required, 'string', 'max:80'],
            'driver' => [$required, 'string', Rule::in(array_keys(CreatorGatewayRegistry::drivers()))],
            'credentials' => [$updating ? 'sometimes' : 'required', 'array'],
            'settings' => ['nullable', 'array'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
