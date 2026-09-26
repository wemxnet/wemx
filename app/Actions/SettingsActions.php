<?php

namespace App\Actions;

use App\Helpers\EnvironmentWriter;
use App\Invoices\InvoiceTheme;
use App\Mail\EmailTheme;
use App\Models\GatewayConfig;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingsActions extends Action
{
    /**
     * Save Application Settings
     *
     * @throws ValidationException
     */
    public static function updateApplicationSettingsAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'app_name' => ['required', 'string'],
            'company_address' => ['nullable', 'string'],
            'language' => ['required', 'string'],
            'currency' => ['required', 'string'],
            'timezone' => ['required', 'string', 'timezone'],
            'email_theme' => ['nullable', 'string', 'max:255', Rule::in(array_keys(EmailTheme::all()))],
        ])->validate();

        Setting::store(self::omitNullValues($validatedData));

        // write the application settings to the environment file for persistence
        try {
            EnvironmentWriter::write([
                'APP_NAME' => $validatedData['app_name'],
                'APP_LOCALE' => $validatedData['language'],
                'APP_TIMEZONE' => $validatedData['timezone'],
            ]);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'app_name' => 'Failed to write application settings to environment file (.env). Make sure the file is writable.',
            ]);
        }
    }

    /**
     * Save Application metrics
     *
     * @throws ValidationException
     */
    public static function updateApplicationMetricsAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'date_format' => ['required', 'string'],
            'renewal_reminder_period' => ['required', 'integer', 'min:0'],
            'suspend_period' => ['required', 'integer', 'min:0', 'max:365'],
            'terminate_period' => ['required', 'integer', 'min:0', 'max:365', 'gt:suspend_period'],
            'enable_delete_terminated_period' => ['required', 'boolean'],
            'delete_terminated_period' => ['required_if:enable_delete_terminated_period,true', 'integer', 'min:0', 'max:365'],
        ])->validate();

        Setting::store(self::omitNullValues($validatedData));
    }

    /**
     * Save Application metrics
     *
     * @throws ValidationException
     */
    public static function updateApplicationInvoicingSettingsAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'invoice_format' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (strpos($value, '{id}') === false) {
                        $fail("The invoice format must at least contain '{id}'.");
                    }
                },
            ],
            'invoice_id_padding' => ['required', 'integer', 'min:0'],
            'billing_from_details' => ['nullable', 'string'],
            'invoice_theme' => ['nullable', 'string', 'max:255', Rule::in(array_keys(InvoiceTheme::all()))],
        ])->validate();

        Setting::store(self::omitNullValues($validatedData));
    }

    /**
     * Save Application Tax Settings
     *
     * @throws ValidationException
     */
    public static function updateApplicationTaxSettingsAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'enable_sales_tax' => ['required', 'boolean'],
            'include_tax_in_price' => ['required', 'boolean'],
            'enable_exempt_sales_tax' => ['required', 'boolean'],
            'verify_vat_id' => ['required', 'boolean'],
            'allow_client_pdf_invoices' => ['required', 'boolean'],
            'excluded_tax_gateways' => ['nullable', 'array'],
            'tax_non_exempt_countries' => ['nullable', 'array'],
        ])->validate();

        // convert the excluded tax gateways to a comma-separated string
        $excludedGatewayIds = array_filter($validatedData['excluded_tax_gateways'] ?? []);

        // If nothing is selected, default to excluding balance gateway from tax calculations.
        if (empty($excludedGatewayIds)) {
            $excludedGatewayIds = GatewayConfig::query()
                ->where('extension_identifier', 'gateway-balance')
                ->pluck('id')
                ->toArray();
        }

        $validatedData['excluded_tax_gateways'] = implode(',', $excludedGatewayIds);

        if (isset($validatedData['tax_non_exempt_countries'])) {
            $validatedData['tax_non_exempt_countries'] = implode(',', $validatedData['tax_non_exempt_countries']);
        } else {
            $validatedData['tax_non_exempt_countries'] = '';
        }

        Setting::store(self::omitNullValues($validatedData));
    }

    /**
     * Save Application Authentication Settings
     *
     * @throws ValidationException
     */
    public static function updateApplicationAuthenticationSettingsAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'enable_registrations' => ['required', 'boolean'],
            'require_address' => ['required', 'boolean'],
            'block_reserved_usernames' => ['required', 'boolean'],
        ])->validate();

        Setting::store(self::omitNullValues($validatedData));
    }
}
