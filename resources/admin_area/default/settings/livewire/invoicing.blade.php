<?php

use App\Invoices\InvoiceTheme;
use App\Models\Setting;
use Livewire\Volt\Component;

new class extends Component
{
    public $invoice_format;

    public $invoice_id_padding;

    public $billing_from_details;

    public string $invoice_theme = 'default';

    public array $invoiceThemes = [];

    public $lastModifiedTimestamps;

    public function mount()
    {
        $this->invoice_format = settings('invoice_format', 'INV-{year}-{id}');
        $this->invoice_id_padding = settings('invoice_id_padding', 4);
        $this->billing_from_details = settings('billing_from_details', '');
        $this->invoice_theme = InvoiceTheme::default()->slug;
        $this->invoiceThemes = InvoiceTheme::options();
        $this->lastModifiedTimestamps = Setting::whereIn('key', ['invoice_theme'])->pluck('updated_at', 'key');
    }

    public function saveChanges()
    {
        Setting::actions()->updateApplicationInvoicingSettingsAsAdmin([
            'invoice_format' => $this->invoice_format,
            'invoice_id_padding' => $this->invoice_id_padding,
            'billing_from_details' => $this->billing_from_details,
            'invoice_theme' => $this->invoice_theme,
        ]);

        $this->dispatch('alert', 'success', 'Settings saved successfully.');
    }
}

?>

<div>
    <x-admin::settings.page-form title="Payments & Invoicing">

            <div class="mb-4">
                <h3 class="card-title">Invoice ID Format</h3>
                <p class="card-subtitle">
                    The format in which invoice IDs are generated, i.e. <code>INV-{year}-{id}</code>. Available placeholders: <code>{id}</code>, <code>{year}</code>, <code>{month}</code>, <code>{day}</code>
                </p>
                <div class="row g-2">
                    <div class="col-auto">
                        <x-admin::form.input wire:model="invoice_format" type="text" />
                    </div>
                    @error('invoice_format')
                        <x-admin::form.error :message="$message" />
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <h3 class="card-title">Invoice ID Padding</h3>
                <p class="card-subtitle">
                    The amount of zeros added to the invoice ID. For example, if you set this to 4, the invoice ID will be <code>0001</code>, <code>0002</code>, etc.
                </p>
                <div class="row g-2">
                    <div class="col-auto">
                        <x-admin::form.input wire:model="invoice_id_padding" type="number" />
                    </div>
                    @error('invoice_id_padding')
                        <x-admin::form.error :message="$message" />
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <h3 class="card-title">Default Invoice Theme</h3>
                <p class="card-subtitle">
                    The layout used for invoice PDFs. Installed invoice themes are listed here.
                </p>
                <div class="row g-2">
                    <div class="col">
                        <x-admin::form.select wire:model="invoice_theme" id="invoice_theme" value="{{ settings('invoice_theme', 'default') }}" :options="$invoiceThemes" />
                    </div>
                    @error('invoice_theme')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">
                            {{ $invoiceThemes[$invoice_theme] ?? 'Default' }}
                            · Last modified {{ isset($lastModifiedTimestamps['invoice_theme']) ? $lastModifiedTimestamps['invoice_theme']->diffForHumans() : 'Never' }}
                        </small>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <h3 class="card-title">Billing From Details</h3>
                <p class="card-subtitle">
                    This block is printed on invoices as your billing sender details. You can include company name, address, tax ID, and contact details.
                </p>
                <div class="row g-2">
                    <div class="col-md-8">
                        <x-admin::form.textarea
                            wire:model="billing_from_details"
                            rows="6"
                            placeholder="Acme Hosting Ltd&#10;123 Cloud Street&#10;London, WC2N 5DU&#10;VAT ID: GB123456789&#10;billing@acmehost.com"
                        />
                    </div>
                    @error('billing_from_details')
                        <x-admin::form.error :message="$message" />
                    @enderror
                </div>
            </div>

    </x-admin::settings.page-form>
</div>
