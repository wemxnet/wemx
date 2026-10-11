<?php

namespace App\Http\Controllers\Admin;

use App\Support\LicensePlanLimits;
use App\Helpers\EnvironmentWriter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LicenseController extends Controller
{
    private const CACHE_KEY = 'license.validation.data';

    private function licenseValidationEndpoint(): string
    {
        return 'https://api-v3.wemx.org/api/v1/licenses/validate';
    }

    private function validateWithServer(string $licenseKey): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->retry(2, 250, throw: false)
            ->post($this->licenseValidationEndpoint(), [
                'license_key' => $licenseKey,
                'domain' => request()->getHost(),
            ]);

        if (! $response->successful() || ! $response->json('success')) {
            return [
                'success' => false,
                'message' => (string) ($response->json('message') ?: 'License key is not valid or has expired.'),
            ];
        }

        $licenseData = $response->json('data.0');
        if (! is_array($licenseData)) {
            return [
                'success' => false,
                'message' => 'License server returned an unexpected response.',
            ];
        }

        $normalized = [
            'license_key' => (string) ($licenseData['license_key'] ?? $licenseKey),
            'plan_name' => (string) data_get($licenseData, 'order.package', 'Not Available'),
            'billing_cycle' => (string) data_get($licenseData, 'order.billing_cycle', 'Not Available'),
            'status' => (string) data_get($licenseData, 'order.status', 'unknown'),
            'expires_at' => data_get($licenseData, 'order.due_date'),
            'domain' => (string) ($licenseData['domain'] ?? ''),
            'email' => (string) data_get($licenseData, 'order.user.email', 'Not Available'),
            'order_id' => data_get($licenseData, 'order.id'),
            'last_checked_at' => now()->toISOString(),
            'limits' => [
                'staff_accounts_limit' => $licenseData['staff_accounts_limit'] ?? null,
                'max_users_limit' => $licenseData['max_users_limit'] ?? null,
                'max_orders_limit' => $licenseData['max_orders_limit'] ?? null,
                'max_gateways_limit' => $licenseData['max_gateways_limit'] ?? null,
                'max_servers_limit' => $licenseData['max_servers_limit'] ?? null,
            ],
        ];

        Cache::forever(self::CACHE_KEY, json_encode($normalized, JSON_THROW_ON_ERROR));

        return [
            'success' => true,
            'data' => $normalized,
        ];
    }

    private function getCachedLicenseData(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (! is_string($cached) || trim($cached) === '') {
            return [];
        }

        try {
            $decoded = json_decode($cached, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function humanDate(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return 'Never';
        }

        try {
            return Carbon::parse($value)->format('d M Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function humanLimit(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Unlimited';
        }

        return (string) $value;
    }

    public function index()
    {
        $licenseData = $this->getCachedLicenseData();
        $licenseData['expires_at_human'] = $this->humanDate($licenseData['expires_at'] ?? null);
        $licenseData['last_checked_at_human'] = $this->humanDate($licenseData['last_checked_at'] ?? null);

        return view('admin::license.index', [
            'licenseData' => $licenseData,
            'licenseKey' => config('app.license_key', ''),
            'limitStaffAccounts' => $this->humanLimit(data_get($licenseData, 'limits.staff_accounts_limit')),
            'limitMaxUsers' => $this->humanLimit(data_get($licenseData, 'limits.max_users_limit')),
            'limitMaxOrders' => $this->humanLimit(data_get($licenseData, 'limits.max_orders_limit')),
            'limitMaxGateways' => $this->humanLimit(data_get($licenseData, 'limits.max_gateways_limit')),
            'limitMaxServers' => $this->humanLimit(data_get($licenseData, 'limits.max_servers_limit')),
            // Read from the same plan data the limits are enforced with.
            'planFeatures' => [
                'Recurring subscriptions' => LicensePlanLimits::subscriptionsEnabled(),
                'Roles & permissions' => LicensePlanLimits::rolesEnabled(),
                'Remove "Powered by WemX"' => LicensePlanLimits::hasFeature(LicensePlanLimits::FEATURE_WHITE_LABEL),
            ],
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:255', 'starts_with:WMX-'],
        ]);

        try {
            $result = $this->validateWithServer((string) $validated['license_key']);
        } catch (\Throwable) {
            return back()->withErrors([
                'license_key' => 'Unable to contact the license server. Please try again.',
            ])->withInput();
        }

        if (! $result['success']) {
            return back()->withErrors([
                'license_key' => (string) $result['message'],
            ])->withInput();
        }

        return back()->with('status', 'License verified successfully and cached.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:255', 'not_in:test'],
        ]);

        try {
            $result = $this->validateWithServer((string) $validated['license_key']);
        } catch (\Throwable) {
            return back()->withErrors([
                'license_key' => 'Unable to contact the license server. Please try again.',
            ])->withInput();
        }

        if (! $result['success']) {
            return back()->withErrors([
                'license_key' => (string) $result['message'],
            ])->withInput();
        }

        EnvironmentWriter::write([
            'LICENSE_KEY' => (string) $result['data']['license_key'],
        ]);
        
        Artisan::call('config:clear');

        return back()->with('status', 'License key saved, validated, and cached successfully.');
    }
}
