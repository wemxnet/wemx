<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\GatewayConfig;

use App\Models\Payment;
use Livewire\Volt\Component;
use Illuminate\View\View;
use App\Models\SalesTaxCountry;

new class extends Component {
    public $gatewayId;

    public $company_name;

    public $tax_id;

    public $country;

    public $region;

    public $zip_code;

    public $first_name;

    public $last_name;

    public $username;

    public $email;

    public $password;

    public $password_confirmation;

    public function incrementQuantity($itemId)
    {
        Cart::actions()->incrementItemQuantity([
            'cart_id' => cart()->id,
            'item_id' => $itemId,
        ]);

        $this->dispatch('cart-updated');
    }

    public function decrementQuantity($itemId)
    {
        Cart::actions()->decrementItemQuantity([
            'cart_id' => cart()->id,
            'item_id' => $itemId,
        ]);

        $this->dispatch('cart-updated');
    }

    public function removeCartItem($itemId)
    {
        Cart::actions()->removeItemFromCart([
            'cart_id' => cart()->id,
            'item_id' => $itemId,
        ]);

        $this->dispatch('cart-updated');
    }

    public function completeCheckout()
    {
        // if cart is empty, do not proceed
        if(!cart() OR cart()->items->isEmpty()) {
            $this->addError('gateway_config_id', 'Your cart is empty.');
            return;
        }

        $payment = Cart::actions()->checkoutAsClient([
            'user_id' => auth()->id(),
            'cart_id' => cart()->id,
            'gateway_config_id' => $this->gatewayId,

            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,

            'company_name' => $this->company_name,
            'tax_id' => $this->tax_id,
            'country' => $this->country,
            'region' => $this->region,
            'zip_code' => $this->zip_code,
        ]);

        $this->redirect(
            route('payments.pay', ['gateway' => $this->gatewayId, 'payment' => $payment->token])
        );
    }

    public function updatedCountry($value)
    {
        $this->country = strtoupper((string) $value);

        if (!in_array($this->country, ['US', 'CA'], true)) {
            $this->region = null;
            $this->resetValidation('region');
        }
    }

    #[\Livewire\Attributes\Computed]
    public function salesTaxTotal()
    {
        return \App\Facades\Tax::calculateSalesTax(
            cart()->total(),
            $this->country,
            $this->region,
            $this->tax_id ?: null,
            $this->gatewayId
        );
    }

    public function mount()
    {
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

        $this->gatewayId = GatewayConfig::where('is_active', true)->first()?->id ?? '';
    }
}
?>

<section>
    <div class="mx-auto max-w-screen-xl px-0 sm:px-4 2xl:px-0">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">Shopping Cart</h2>

        <div class="mt-6 sm:mt-8 md:gap-6 lg:flex lg:items-start xl:gap-8">
            <div class="mx-auto w-full flex-none lg:max-w-2xl xl:max-w-4xl">
                <div class="space-y-6">

                    @if(cart())
                        @if(cart()->items->isEmpty())
                            <x-theme::card>
                                <x-theme::text.p text="You currently don't have any items in your cart" />
                            </x-theme::card>
                        @endif

                        @error('cart_id')
                            <x-theme::alert.danger>
                                <x-theme::text.p text="{{ $message }}" />
                            </x-theme::alert.danger>
                        @enderror

                        @foreach(cart()->items as $item)
                            <div
                                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 md:p-6">
                                <div class="space-y-4 md:flex md:items-center md:justify-between md:gap-6 md:space-y-0">
                                    <a href="#" class="shrink-0 md:order-1">
                                        <img class="h-20 w-20"
                                             src="{{ $item->getIcon() }}"
                                             alt="Image"/>
                                    </a>

                                    <label for="counter-input" class="sr-only">Choose quantity:</label>
                                    <div class="flex items-center justify-between md:order-3 md:justify-end">
                                        <div class="flex items-center">
                                            <button
                                                @if($item->quantity <= 1) wire:confirm="Are you sure you want to remove this item?"
                                                @endif wire:click="decrementQuantity('{{ $item->id }}')" type="button"
                                                id="decrement-button" data-input-counter-decrement="counter-input"
                                                class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:focus:ring-gray-700">
                                                <svg class="h-2.5 w-2.5 text-gray-900 dark:text-white"
                                                     aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                     viewBox="0 0 18 2">
                                                    <path stroke="currentColor" stroke-linecap="round"
                                                          stroke-linejoin="round" stroke-width="2" d="M1 1h16"/>
                                                </svg>
                                            </button>
                                            <input type="text" disabled id="counter-input" data-input-counter
                                                   class="w-10 shrink-0 border-0 bg-transparent text-center text-sm font-medium text-gray-900 focus:outline-none focus:ring-0 dark:text-white"
                                                   placeholder="" value="{{ $item->quantity }}" required/>
                                            <button wire:click="incrementQuantity('{{ $item->id }}')" type="button"
                                                    id="increment-button" data-input-counter-increment="counter-input"
                                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:focus:ring-gray-700">
                                                <svg class="h-2.5 w-2.5 text-gray-900 dark:text-white"
                                                     aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                     viewBox="0 0 18 18">
                                                    <path stroke="currentColor" stroke-linecap="round"
                                                          stroke-linejoin="round" stroke-width="2" d="M9 1v16M1 9h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="text-end md:order-4 md:w-32">
                                            <p class="text-base font-bold text-gray-900 dark:text-white">{{ price($item->totalWithOptions()) }}</p>
                                        </div>
                                    </div>

                                    <div class="w-full min-w-0 flex-1 space-y-4 md:order-2 md:max-w-md">
                                        <a href="#"
                                           class="text-base font-medium text-gray-900 hover:underline dark:text-white">{{ $item->getName() }} {{ price($item->total()) }}</a>

                                        @if($item->options->isNotEmpty())
                                        <div class="flex items-center gap-2">
                                            <ul class="max-w-md space-y-1 text-gray-500 list-disc list-inside dark:text-gray-400">
                                                @foreach($item->options as $option)
                                                    <li>
                                                        {{ $option->name }} @if(($display = $option->displayValueForCart()) !== '')
                                                            ({{ $display }})
                                                        @endif @if($option->price)
                                                            - {{ price($option->price) }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        @endif

                                        <div class="flex items-center gap-4">
                                            <button type="button"
                                                    wire:confirm="Are you sure you want to remove this item?"
                                                    wire:click="removeCartItem('{{ $item->id }}')"
                                                    class="inline-flex items-center text-sm font-medium text-red-600 hover:underline dark:text-red-500">
                                                <svg class="me-1.5 h-5 w-5" aria-hidden="true"
                                                     xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                     fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round"
                                                          stroke-linejoin="round" stroke-width="2"
                                                          d="M6 18 17.94 6M18 18 6.06 6"/>
                                                </svg>
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-md font-semibold text-gray-900 dark:text-white">Your cart is empty</p>
                        </div>
                    @endif

                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl mt-6 mb-2">Payment Method</h2>
                    @error('gateway_config_id')
                        <x-theme::form.error :text="$message" />
                    @enderror

                    @if(GatewayConfig::where('type', 'payment')->where('is_active', true)->count() == 0)
                        <x-theme::alert.warning>
                            <x-theme::text.p text="No payment gateways are currently available. Please contact support." />
                        </x-theme::alert.warning>
                    @endif

                    <div class="space-y-4 border-b border-gray-200 py-5 dark:border-gray-700">
                        <x-theme::checkout.gateway-list />
                    </div>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl mt-6 mb-6">Billing Details</h2>
                    <x-theme::card>
                        <x-theme::checkout.billing-fields :company-name="$company_name" :country="$country" />
                    </x-theme::card>
                </div>

                @if(auth()->guest())
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl mt-6 mb-6">Account Details</h2>
                    <x-theme::card>
                        <div class="my-2 grid gap-5 sm:grid-cols-2">
                            <div class="mb-4">
                                <x-theme::form.label for="first_name" text="First Name" />
                                <x-theme::form.input type="text" placeholder="First Name" wire:model="first_name" id="first_name"/>
                                @error('first_name')
                                <x-theme::form.error :text="$message" />
                                @enderror
                            </div>

                            <div class="mb-4">
                                <x-theme::form.label for="last_name" text="Last Name" />
                                <x-theme::form.input type="text" placeholder="Last Name" wire:model="last_name" id="last_name"/>
                                @error('last_name')
                                <x-theme::form.error :text="$message" />
                                @enderror
                            </div>
                        </div>
                        <div class="my-2 grid gap-5 sm:grid-cols-1">
                            <div class="mb-4">
                                <x-theme::form.label for="username" text="Username" />
                                <x-theme::form.input type="text" placeholder="Username" wire:model="username" id="username"/>
                                @error('username')
                                <x-theme::form.error :text="$message" />
                                @enderror
                            </div>
                        </div>
                        <div class="my-2 grid gap-5 sm:grid-cols-1">
                            <div class="mb-4">
                                <x-theme::form.label for="email" text="Email" />
                                <x-theme::form.input type="email" placeholder="Email" wire:model="email" id="email"/>
                                @error('email')
                                <x-theme::form.error :text="$message" />
                                @enderror
                            </div>
                        </div>
                        <div class="my-2 grid gap-5 sm:grid-cols-2 mb-4">
                            <div class="mb-4">
                                <x-theme::form.label for="password" text="Password" />
                                <x-theme::form.input type="password" placeholder="Password" wire:model="password" id="password"/>
                                @error('password')
                                <x-theme::form.error :text="$message" />
                                @enderror
                            </div>
                            <div class="mb-4">
                                <x-theme::form.label for="password_confirmation" text="Confirm Password" />
                                <x-theme::form.input type="password" placeholder="Confirm Password" wire:model="password_confirmation" id="password_confirmation"/>
                                @error('password_confirmation')
                                <x-theme::form.error :text="$message" />
                                @enderror
                            </div>
                        </div>
                        <x-theme::text.p class="text-sm">Already have an account?
                            <x-theme::text.link href="{{ route('login') }}" wire:navigate>Sign In</x-theme::text.link>
                        </x-theme::text.p>
                    </x-theme::card>
                </div>
                @endif
            </div>

            <div class="mx-auto mt-6 max-w-4xl flex-1 space-y-6 lg:mt-0 lg:w-full">
                <div
                    class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                    <p class="text-xl font-semibold text-gray-900 dark:text-white">Order summary</p>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <dl class="flex items-center justify-between gap-4">
                                <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Total</dt>
                                <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price(cart()->total()) }}</dd>
                            </dl>

                            @if(settings('enable_sales_tax', false))
                                <dl class="flex items-center justify-between gap-4">
                                    <dt class="text-base font-normal text-gray-500 dark:text-gray-400">{{ $this->salesTaxTotal['tax_name'] }} {{ $this->salesTaxTotal['tax_rate'] != 0 ? "({$this->salesTaxTotal['tax_rate']}%)" : '' }}</dt>
                                    <dd class="text-base font-medium text-gray-900 dark:text-white">{{ ($this->salesTaxTotal['tax_amount'] != 0) ? price($this->salesTaxTotal['tax_amount']) : '-' }}</dd>
                                </dl>
                            @endif
                        </div>

                        <dl class="flex items-center justify-between gap-4 border-t border-gray-200 pt-2 dark:border-gray-700">
                            <dt class="text-base font-bold text-gray-900 dark:text-white">Total</dt>
                            <dd class="text-base font-bold text-gray-900 dark:text-white">{{ settings('enable_sales_tax', false) ? price($this->salesTaxTotal['amount_after_tax']) : price(cart()->total()) }}</dd>
                        </dl>
                    </div>

                    <button type="button" wire:click="completeCheckout()"
                            class="flex w-full items-center justify-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                        Proceed to Checkout
                    </button>

                    <div class="flex items-center justify-center gap-2">
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400"> or </span>
                        <a href="{{ route('categories.index') }}" title="" wire:navigate
                           class="inline-flex items-center gap-2 text-sm font-medium text-primary-700 underline hover:no-underline dark:text-primary-500">
                            Continue Shopping
                            <svg class="h-5 w-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                 viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2" d="M19 12H5m14 0-4 4m4-4-4-4"/>
                            </svg>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
