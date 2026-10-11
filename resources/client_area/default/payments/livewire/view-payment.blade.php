<?php

use App\Models\Payment;
use Livewire\Volt\Component;
use App\Models\GatewayConfig;
use App\Handlers\BalanceTopupHandler;

new class extends Component {
    public $payment;

    public $gatewayId = '';

    public $company_name = '';

    public $tax_id = '';

    public $country = '';

    public $region = '';

    public $zip_code = '';

    public function mount($payment)
    {
        $this->payment = $payment;

        $gatewayQuery = GatewayConfig::query()
            ->where('type', 'payment')
            ->where('is_active', true);

        // Balance top-up payments cannot be paid with existing account balance.
        if ($payment->handler === BalanceTopupHandler::class) {
            $gatewayQuery->where('extension_identifier', '!=', 'gateway-balance');
        }

        $this->gatewayId = $payment->gateway_config_id ?? $gatewayQuery->first()?->id ?? '';

        if(auth()->user()) {
            // if user has previous payment, that has a tax details model model relationship, use that to prefill the tax details
            $lastPaymentWithTax = auth()->user()->payments()->whereHas('taxDetails')->latest()->first();

            if($lastPaymentWithTax) {
                $this->company_name = $lastPaymentWithTax->taxDetails['company_name'] ?? '';
                $this->tax_id = $lastPaymentWithTax->taxDetails['tax_id'] ?? '';
                $this->region = $lastPaymentWithTax->taxDetails['region'] ?? '';
                $this->zip_code = $lastPaymentWithTax->taxDetails['zip_code'] ?? '';
                $this->country = $lastPaymentWithTax->taxDetails['country'] ?? '';
            } else {
                $this->company_name = auth()->user()->address->company_name ?? '';
                $this->tax_id = auth()->user()->address->tax_id ?? '';
                $this->region = auth()->user()->address->region ?? '';
                $this->zip_code = auth()->user()->address->zip_code ?? '';
                $this->country = auth()->user()->address->country ?? '';
            }
        }
    }

    #[\Livewire\Attributes\Computed]
    public function salesTaxTotal()
    {
        return \App\Facades\Tax::calculateSalesTax(
            $this->payment->subtotal,
            $this->country,
            $this->region,
            $this->tax_id,
            $this->gatewayId
        );
    }

    public function payPayment()
    {
        $payment = Payment::actions()->calculateSalesTaxAsClient([
            'payment_id' => $this->payment->id,
            'gateway_config_id' => $this->gatewayId,
            'company_name' => $this->company_name,
            'tax_id' => $this->tax_id,
            'country' => $this->country,
            'region' => $this->region,
            'zip_code' => $this->zip_code,
        ]);

        $this->redirect(route('payments.pay', [
            'payment' => $payment->token,
            'gateway' => $this->gatewayId,
        ]));
    }
}

?>

<div class="mx-auto max-w-screen-xl px-0 sm:px-4 2xl:px-0">
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">Payment</h2>
            @if(settings('allow_client_pdf_invoices', false))
                <a href="{{ route('payments.view.invoice-pdf', ['payment' => $payment->token]) }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-700">
                    Download Invoice PDF
                </a>
            @endif
        </div>
        <p class="mt-2 text-base text-gray-500 dark:text-gray-400">
            {{ $payment->description }}
        </p>

        @if($payment->isNotPaid())
            <div class="mt-6 sm:mt-8 lg:flex lg:items-start lg:gap-8">
            <form wire:submit="payPayment()" class="w-full space-y-6 lg:max-w-xl">
                @error('gateway_config_id')
                <x-theme::form.error :text="$message"/>
                @enderror
                <x-theme::checkout.gateway-list :exclude-balance-gateway="true" :handler="$payment->handler" />

                <hr class="h-px my-8 bg-gray-200 border-0 dark:bg-gray-700">

                <x-theme::checkout.billing-fields :company-name="$company_name" :country="$country" />

                <button type="submit"
                        class="flex w-full items-center justify-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4  focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                    Pay now
                </button>
            </form>

            <div class="mt-6 grow space-y-6 sm:mt-8 lg:mt-0">
                <div
                    class="space-y-4 rounded-lg border border-gray-100 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-xl font-semibold text-gray-900 dark:text-white">Summary</p>
                    <div class="space-y-2">
                        <dl class="flex items-center justify-between gap-4">
                            <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Subtotal</dt>
                            <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($payment->subtotal, in: $payment->currency) }}</dd>
                        </dl>
                        @if(settings('enable_sales_tax', false))
                            <dl class="flex items-center justify-between gap-4">
                                <dt class="text-base font-normal text-gray-500 dark:text-gray-400">{{ $this->salesTaxTotal['tax_name'] }} {{ $this->salesTaxTotal['tax_rate'] != 0 ? "({$this->salesTaxTotal['tax_rate']}%)" : '' }}</dt>
                                <dd class="text-base font-medium text-gray-900 dark:text-white">{{ ($this->salesTaxTotal['tax_amount'] != 0) ? price($this->salesTaxTotal['tax_amount'], in: $payment->currency) : '-' }}</dd>
                            </dl>
                        @endif
                    </div>

                    <dl class="flex items-center justify-between gap-4 border-t border-gray-200 pt-2 dark:border-gray-700">
                        <dt class="text-base font-bold text-gray-900 dark:text-white">Total</dt>
                        <dd class="text-base font-bold text-gray-900 dark:text-white">{{ settings('enable_sales_tax', false) ? price($this->salesTaxTotal['amount_after_tax'], in: $payment->currency) : price($payment->subtotal, in: $payment->currency) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        @else
            <div class="grid md:grid-cols-2 md:gap-12 mt-6 sm:mt-8">
                <div class="mb-6 md:mb-8">
                    <div class="divide-y divide-gray-200 dark:divide-gray-800 mb-6 md:mb-8">
                        <dl class="sm:flex items-center justify-between gap-4 pb-3">
                            <dt class="font-normal mb-1 sm:mb-0 text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="font-medium text-gray-900 dark:text-white sm:text-end">
                                @if($payment->isPaid())
                                    <x-theme::badge.success text="Paid"/>
                                @else
                                    <x-theme::badge.warning :text="ucfirst($payment->status)"/>
                                @endif
                            </dd>
                        </dl>
                        <dl class="sm:flex items-center justify-between gap-4 py-3">
                            <dt class="font-normal mb-1 sm:mb-0 text-gray-500 dark:text-gray-400">Paid at</dt>
                            <dd class="font-medium text-gray-900 dark:text-white sm:text-end">
                                {{ $payment->paid_at ? $payment->paid_at->format(settings('date_format', 'D M Y H:i')) : '-' }}
                            </dd>
                        </dl>
                        <dl class="sm:flex items-center justify-between gap-4 py-3">
                            <dt class="font-normal mb-1 sm:mb-0 text-gray-500 dark:text-gray-400">Created at</dt>
                            <dd class="font-medium text-gray-900 dark:text-white sm:text-end">
                                {{ $payment->created_at ? $payment->created_at->format(settings('date_format', 'D M Y H:i')) : '-' }}
                            </dd>
                        </dl>
                        <dl class="sm:flex items-center justify-between gap-4 py-3">
                            <dt class="font-normal mb-1 sm:mb-0 text-gray-500 dark:text-gray-400">Invoice ID</dt>
                            <dd class="font-medium text-gray-900 dark:text-white sm:text-end">
                                {{ $payment->invoice_id ?? '-' }}
                            </dd>
                        </dl>
                        <dl class="sm:flex items-center justify-between gap-4 py-3">
                            <dt class="font-normal mb-1 sm:mb-0 text-gray-500 dark:text-gray-400">Payment Method</dt>
                            <dd class="font-medium text-gray-900 dark:text-white sm:text-end">
                                {{ $payment->gatewayConfig?->display_name ?? 'N/A' }}
                            </dd>
                        </dl>
                        <dl class="sm:flex items-center justify-between gap-4 py-3">
                            <dt class="font-normal mb-1 sm:mb-0 text-gray-500 dark:text-gray-400">Transaction ID</dt>
                            <dd class="font-medium text-gray-900 dark:text-white sm:text-end">
                                {{ $payment->transaction_id ?? '-' }}
                            </dd>
                        </dl>
                        <dl class="sm:flex items-center justify-between gap-4 py-3">
                            <dt class="font-normal mb-1 sm:mb-0 text-gray-500 dark:text-gray-400">Currency</dt>
                            <dd class="font-medium text-gray-900 dark:text-white sm:text-end">
                                {{ $payment->currency ?? '-' }}
                            </dd>
                        </dl>
                    </div>
                    <div class="flex items-center space-x-4">
                        @if($payment->payable_type == 'App\Models\Order' AND $payment->payable)
                            <a href="{{ route('orders.view', ['order' => $payment->payable_id]) }}" wire:navigate class="text-white bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">View Order</a>
                        @endif
                        <a href="/" wire:navigate class="py-2.5 px-5 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-primary-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700">Return to Dashboard</a>
                    </div>
                </div>
                <div class="">
                    <div class="mt-6 mb-4 grow space-y-6 sm:mt-8 lg:mt-0">
                        <div
                            class="space-y-4 rounded-lg border border-gray-100 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xl font-semibold text-gray-900 dark:text-white">Summary</p>
                            <div class="space-y-2">
                                <dl class="flex items-center justify-between gap-4">
                                    <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Subtotal</dt>
                                    <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($payment->subtotal, in: $payment->currency) }}</dd>
                                </dl>
                                @if(settings('enable_sales_tax', false))
                                    <dl class="flex items-center justify-between gap-4">
                                        <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Sales Tax</dt>
                                        <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($payment->tax, in: $payment->currency) }}</dd>
                                    </dl>
                                @endif
                                <dl class="flex items-center justify-between gap-4">
                                    <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Discount</dt>
                                    <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($payment->discount, in: $payment->currency) }}</dd>
                                </dl>
                            </div>

                            <dl class="flex items-center justify-between gap-4 border-t border-gray-200 pt-2 dark:border-gray-700">
                                <dt class="text-base font-bold text-gray-900 dark:text-white">Total</dt>
                                <dd class="text-base font-bold text-gray-900 dark:text-white">{{ price($payment->total, in: $payment->currency) }}</dd>
                            </dl>
                        </div>
                    </div>
                    @if($payment->taxDetails)
                    <div class="mt-6 grow space-y-6 sm:mt-8 lg:mt-0">
                        <div
                            class="space-y-4 rounded-lg border border-gray-100 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xl font-semibold text-gray-900 dark:text-white">Tax Details</p>
                            <div class="space-y-2">
                                @if($payment->taxDetails['company_name'])
                                    <dl class="flex items-center justify-between gap-4">
                                        <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Company Name</dt>
                                        <dd class="text-base font-medium text-gray-900 dark:text-white">{{ $payment->taxDetails['company_name'] }}</dd>
                                    </dl>
                                @endif
                                @if($payment->taxDetails['tax_id'])
                                    <dl class="flex items-center justify-between gap-4">
                                        <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Tax ID</dt>
                                        <dd class="text-base font-medium text-gray-900 dark:text-white">{{ $payment->taxDetails['tax_id'] }}</dd>
                                    </dl>
                                @endif
                                @if($payment->taxDetails['country'])
                                <dl class="flex items-center justify-between gap-4">
                                    <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Country</dt>
                                    <dd class="text-base font-medium text-gray-900 dark:text-white">
                                        {{ \App\Facades\World::countryName($payment->taxDetails['country']) }}
                                    </dd>
                                </dl>
                                @endif
                                @if($payment->taxDetails['region'])
                                <dl class="flex items-center justify-between gap-4">
                                    <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Region / State</dt>
                                    <dd class="text-base font-medium text-gray-900 dark:text-white">{{ $payment->taxDetails['region'] }}</dd>
                                </dl>
                                @endif
                                @if($payment->taxDetails['tax_name'])
                                    <dl class="flex items-center justify-between gap-4">
                                        <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Tax Name</dt>
                                        <dd class="text-base font-medium text-gray-900 dark:text-white">{{ $payment->taxDetails['tax_name'] }} {{ $payment->taxDetails['tax_rate'] ?? '0' }}%</dd>
                                    </dl>
                                @endif
                                <dl class="flex items-center justify-between gap-4">
                                    <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Tax Total</dt>
                                    <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($payment->tax, in: $payment->currency) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
