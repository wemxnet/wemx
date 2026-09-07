<?php

namespace Extensions\Servers\Cpanel\Actions;

use App\Actions\Action;
use App\Models\Order;
use App\Models\User;
use Extensions\Servers\Cpanel\Server;
use Extensions\Servers\Cpanel\Support\CpanelAccountManager;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CpanelAccountActions extends Action
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function loginAsClient(array $input): array
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);

        $this->assertFlag($order, 'allow_login', 'cPanel login is not enabled for this package.');

        return CpanelAccountManager::for($order->package->serverConnection)->loginUrl($order);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function loginAsAdmin(array $input): array
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $order = $this->adminOrder($validated['order_id'], $validated['user_id']);

        return CpanelAccountManager::for($order->package->serverConnection)->loginUrl($order);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function changePasswordAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
        ])->validate();

        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);

        $this->assertFlag($order, 'allow_password_change', 'Password changes are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->changePassword($order, $validated['password']);
        $order->updateExternalPassword($validated['password']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createEmailAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'email' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'quota' => ['nullable', 'numeric', 'min:0'],
            'domain' => ['nullable', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_email', 'Email accounts are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->createEmailAccount($order, $validated);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteEmailAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'email' => ['required', 'string'],
            'domain' => ['nullable', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_email', 'Email accounts are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)
            ->deleteEmailAccount($order, $validated['email'], $validated['domain'] ?? null);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createFtpAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'user' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'quota' => ['nullable', 'numeric', 'min:0'],
            'homedir' => ['nullable', 'string', 'max:120'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_ftp', 'FTP accounts are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->createFtpAccount($order, $validated);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteFtpAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'user' => ['required', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_ftp', 'FTP accounts are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->deleteFtpAccount($order, $validated['user']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createDatabaseAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_databases', 'Databases are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->createDatabase($order, $validated['name']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteDatabaseAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_databases', 'Databases are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->deleteDatabase($order, $validated['name']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createDatabaseUserAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_databases', 'Databases are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)
            ->createDatabaseUser($order, $validated['name'], $validated['password']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteDatabaseUserAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_databases', 'Databases are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->deleteDatabaseUser($order, $validated['name']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function grantDatabasePrivilegesAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'user' => ['required', 'string'],
            'database' => ['required', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_databases', 'Databases are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)
            ->grantDatabasePrivileges($order, $validated['user'], $validated['database']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createAddonDomainAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'domain' => ['required', 'string', 'max:191'],
            'subdomain' => ['nullable', 'string', 'max:64'],
            'dir' => ['nullable', 'string', 'max:191'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_domains', 'Domain tools are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->createAddonDomain($order, $validated);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteAddonDomainAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'domain' => ['required', 'string'],
            'subdomain' => ['required', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_domains', 'Domain tools are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)
            ->deleteAddonDomain($order, $validated['domain'], $validated['subdomain']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createSubdomainAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'subdomain' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'rootdomain' => ['nullable', 'string'],
            'dir' => ['nullable', 'string', 'max:191'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_domains', 'Domain tools are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->createSubdomain($order, $validated);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function deleteSubdomainAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'domain' => ['required', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_domains', 'Domain tools are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->deleteSubdomain($order, $validated['domain']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function parkDomainAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'domain' => ['required', 'string', 'max:191'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_domains', 'Domain tools are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->parkDomain($order, $validated['domain']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function unparkDomainAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'domain' => ['required', 'string'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_domains', 'Domain tools are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->unparkDomain($order, $validated['domain']);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function requestAutoSslAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_ssl', 'SSL tools are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->requestAutoSsl($order);

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createBackupAsClient(array $input): Order
    {
        $validated = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'user_id' => ['required', 'exists:users,id'],
        ])->validate();

        $order = $this->toolOrder($validated, 'allow_backups', 'Backups are not enabled for this package.');

        CpanelAccountManager::for($order->package->serverConnection)->createHomeBackup($order);

        return $order->fresh();
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
            'external_id' => (string) $data['username'],
            'data' => $accountData,
        ]);

        $existing = $order->getExternalUser();

        if ($existing) {
            $existing->update([
                'external_id' => (string) $data['username'],
                'username' => $data['username'] ?? $existing->username,
                'password' => $password ?? $existing->password,
                'data' => $accountData,
            ]);

            return;
        }

        $order->createExternalUser([
            'external_id' => (string) $data['username'],
            'username' => $data['username'],
            'password' => $password ?? 'unknown',
            'data' => $accountData,
        ]);
    }

    public function rememberError(Order $order, string $message): void
    {
        $data = $order->data ?? [];
        $data['last_error'] = $message;
        $order->update(['data' => $data]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function toolOrder(array $validated, string $flag, string $message): Order
    {
        $order = $this->authorizedOrder($validated['order_id'], $validated['user_id'], requireActive: true);
        $this->assertFlag($order, $flag, $message);

        return $order;
    }

    protected function assertFlag(Order $order, string $flag, string $message): void
    {
        if ((string) $order->option($flag, '1') !== '1') {
            throw ValidationException::withMessages([
                'order_id' => $message,
            ]);
        }
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

        if (! Server::usesCpanel($order)) {
            throw ValidationException::withMessages([
                'order_id' => 'This order is not provisioned on cPanel.',
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
                'order_id' => 'This action is only available while the account is active.',
            ]);
        }

        if (! $order->external_id && empty($order->data['username'])) {
            throw ValidationException::withMessages([
                'order_id' => 'This cPanel account has not finished provisioning yet.',
            ]);
        }

        return $order;
    }

    protected function adminOrder(int|string $orderId, int|string $userId): Order
    {
        $order = Order::query()->with(['package.serverConnection', 'user'])->find($orderId);
        $user = User::query()->find($userId);

        if (! $order || ! Server::usesCpanel($order)) {
            throw ValidationException::withMessages([
                'order_id' => 'This order is not provisioned on cPanel.',
            ]);
        }

        if (! $user || (! $user->isAdmin() && ! $user->hasPermission('admin.orders.view'))) {
            throw ValidationException::withMessages([
                'order_id' => 'You do not have access to this order.',
            ]);
        }

        if (! $order->external_id && empty($order->data['username'])) {
            throw ValidationException::withMessages([
                'order_id' => 'This cPanel account has not finished provisioning yet.',
            ]);
        }

        return $order;
    }
}
