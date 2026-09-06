<?php

namespace Extensions\Servers\Proxmox\Actions;

use App\Actions\Action;
use App\Models\Order;
use App\Models\User;
use Extensions\Servers\Proxmox\Server;
use Extensions\Servers\Proxmox\Support\OsTemplates;
use Extensions\Servers\Proxmox\Support\ProxmoxVmManager;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProxmoxVmActions extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function powerAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'action' => ['required', 'in:start,shutdown,stop,reboot'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);

        ProxmoxVmManager::for($order->package->serverConnection)->power($order, $validated['action']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function changeHostnameAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'hostname' => ['required', 'string', 'min:1', 'max:63', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*[A-Za-z0-9]$|^[A-Za-z0-9]$/'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);
        $data = ProxmoxVmManager::for($order->package->serverConnection)->changeHostname($order, $validated['hostname']);

        $order->update(['data' => $data]);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function reinstallAsClient(array $input): Order
    {
        $order = $this->authorizedOrder($input['order_id'] ?? 0, $input['user_id'] ?? 0, requireActive: true);

        if ((string) $order->option('allow_reinstall', '1') !== '1') {
            throw ValidationException::withMessages([
                'os_template' => 'Reinstalling is not enabled for this package.',
            ]);
        }

        $templates = OsTemplates::options((string) $order->option('os_templates', ''));

        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'os_template' => ['nullable', 'string', function ($attribute, $value, $fail) use ($templates) {
                if ($value && $templates !== [] && ! array_key_exists($value, $templates)) {
                    $fail('The selected operating system is not available.');
                }
            }],
        ])->validate();

        $data = ProxmoxVmManager::for($order->package->serverConnection)->reinstall($order, $validated['os_template'] ?? null);

        $this->storeProvisionedState($order, $data);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createSnapshotAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string', 'min:1', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'description' => ['nullable', 'string', 'max:120'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);
        $limit = (int) $order->option('snapshot_limit', 3);

        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'name' => 'Snapshots are not enabled for this package.',
            ]);
        }

        $manager = ProxmoxVmManager::for($order->package->serverConnection);

        if (count($manager->snapshots($order)) >= $limit) {
            throw ValidationException::withMessages([
                'name' => "You have reached the snapshot limit of {$limit}.",
            ]);
        }

        $manager->createSnapshot($order, $validated['name'], $validated['description'] ?? null);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteSnapshotAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);

        ProxmoxVmManager::for($order->package->serverConnection)->deleteSnapshot($order, $validated['name']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function restoreSnapshotAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);

        ProxmoxVmManager::for($order->package->serverConnection)->restoreSnapshot($order, $validated['name']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function consoleAsClient(array $input): array
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);

        if ((string) $order->option('allow_console', '1') !== '1') {
            throw ValidationException::withMessages([
                'order_id' => 'Console access is not enabled for this package.',
            ]);
        }

        return ProxmoxVmManager::for($order->package->serverConnection)->console($order);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeProvisionedState(Order $order, array $data): void
    {
        $password = $data['password'] ?? null;
        $accountData = $data;
        unset($accountData['password']);

        $order->update([
            'external_id' => (string) $data['vmid'],
            'data' => $accountData,
        ]);

        $existing = $order->getExternalUser();

        if ($existing) {
            $existing->update([
                'external_id' => (string) $data['vmid'],
                'username' => $data['username'] ?? $existing->username,
                'password' => $password ?? $existing->password,
                'data' => $accountData,
            ]);

            return;
        }

        $order->createExternalUser([
            'external_id' => (string) $data['vmid'],
            'username' => $data['username'] ?? 'root',
            'password' => $password ?? 'unknown',
            'data' => $accountData,
        ]);
    }

    protected function authorizedOrder(int|string $orderId, int|string $userId, bool $requireActive = false): Order
    {
        $order = Order::query()->with(['package.serverConnection', 'members', 'user'])->find($orderId);
        $user = User::query()->find($userId);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found.',
            ]);
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found.',
            ]);
        }

        if (! Server::usesProxmox($order)) {
            throw ValidationException::withMessages([
                'order_id' => 'This order is not provisioned on Proxmox.',
            ]);
        }

        $isOwner = (int) $order->user_id === (int) $user->id;
        $isMember = $order->members()
            ->where('status', 'active')
            ->where('user_id', $user->id)
            ->exists();

        if (! $isOwner && ! $isMember) {
            throw ValidationException::withMessages([
                'order_id' => 'You do not have access to this order.',
            ]);
        }

        if ($requireActive && $order->status !== 'active') {
            throw ValidationException::withMessages([
                'order_id' => 'This action is only available while the server is active.',
            ]);
        }

        if (! $order->external_id && empty($order->data['vmid'])) {
            throw ValidationException::withMessages([
                'order_id' => 'This virtual machine has not finished provisioning yet.',
            ]);
        }

        return $order;
    }
}
