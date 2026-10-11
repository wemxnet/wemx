<?php

use App\Models\Cart;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Illuminate\View\View;

use App\Models\Package;
use App\Models\GatewayConfig;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Illuminate\Support\Arr;

new class extends Component {
    #[Locked]
    public $package;

    #[Url]
    public $packagePriceId;

    #[Url]
    public $config_options = [];

    public function mount($packageSlug)
    {
        $this->package = Package::where('slug', $packageSlug)->firstOrFail();
        abort_unless($this->package->isVisibleToUser(auth()->user()), 404);

        $firstPrice = $this->package->prices->first();

        if ($firstPrice AND !$this->packagePriceId) {
            $this->packagePriceId = $firstPrice->id;
        }
    }

    public function addToCart()
    {
        // if server connection has prevent_purchasing enabled, and the server connection is not healthy, prevent adding to cart
        if ($this->package->serverConnection->prevent_purchasing AND !$this->package->serverConnection->isHealthy()) {
            $this->addError('package_error', 'Could not establish connection to third party server. Please contact support or try again later.');
            return;
        }

        try {
            // if method exists, call the event to validate the package before adding to cart
            if (method_exists($this->package->serverConnection->server->functions(), 'eventAddToCart')) {
                $this->package->serverConnection->server->functions()->eventAddToCart(
                    $this->package,
                    $this->config_options,
                );
            }
        } catch (\Exception $e) {
            // If validation fails, we can handle it here or let the Livewire component handle it
            $this->addError('package_error', $e->getMessage());
            return;
        }

        Cart::actions()->addPackageToCart([
            'cart_id' => cart()->id,
            'package_price_id' => $this->packagePriceId,
            'config_options' => $this->config_options,
        ]);

        $this->redirect(route('cart'), true);
    }

    #[Computed]
    public function packagePrice()
    {
        return $this->package->prices()->find($this->packagePriceId);
    }

    #[Computed]
    public function calculateConfigOptionCost()
    {
        try {
            return $this->package->configurableOptionCalculator($this->config_options, $this->packagePrice->period_in_days);
        } catch (ValidationException $e) {
            // If validation fails, return an empty array
            return [
                'total' => 0,
                'breakdown' => [],
            ];
        }
    }

    public function rendering($view)
    {
        if ($this->config_options == []) {
            $this->config_options = [];

            foreach ($this->package->configOptions as $option) {
                // Sets nested arrays using dot notation: "a.b.c" => ['a' => ['b' => ['c' => value]]]
                Arr::set($this->config_options, $option->key, $option->default_value ?? '');
            }
        }
    }
};
?>

<section>
    <div class="mx-auto max-w-screen-xl px-0 sm:px-4 2xl:px-0">

        <div class="mb-4 flex items-start gap-3">
            <img class="h-14 w-14 shrink-0 rounded-sm sm:h-16 sm:w-16" src="{{ $package->icon() }}" alt="Large avatar">

            <div class="min-w-0">
                <h2 class="break-words text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">{{ $package->name }}</h2>
                <p class="break-words text-base text-gray-500 dark:text-gray-400 sm:text-lg lg:mb-0 lg:max-w-2xl">{{ $package->short_description }}</p>
            </div>
        </div>

        <div class=" md:gap-6 lg:flex lg:items-start xl:gap-8">
            <div class="mx-auto w-full flex-none lg:max-w-2xl xl:max-w-4xl">
                @error('package_error')
                    <x-theme::alert.danger :text="$message" />
                @enderror

                <x-theme::card class="mb-6">
                    <div class="format format-sm format-blue dark:format-invert ">
                        {!! Str::markdown(($package->description) ?? 'No description provided') !!}
                    </div>
                </x-theme::card>

                <hr class="h-px my-4 bg-gray-200 border-0 dark:bg-gray-700">

                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Billing Cycle</h2>
                    <ul class="grid w-full gap-6 md:grid-cols-3">

                        @foreach($package->prices as $price)
                            <li>
                                <input type="radio" wire:model.live="packagePriceId"
                                       id="package_price_id{{ $price->id }}" value="{{ $price->id }}"
                                       class="hidden peer" required/>
                                <label for="package_price_id{{ $price->id }}"
                                       class="inline-flex items-center justify-between w-full p-5 text-gray-500 bg-white border border-gray-200 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-700 dark:peer-checked:text-blue-500 peer-checked:border-blue-600 dark:peer-checked:border-blue-600 peer-checked:text-blue-600 hover:text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:bg-gray-800 dark:hover:bg-gray-700">
                                    <div class="block">
                                        <div class="w-full text-lg font-semibold">{{ price($price->price) }}
                                            / {{ $price->cycle() }}</div>
                                        <div class="w-full">{{ $price->short_description }}</div>
                                    </div>
                                    <svg class="w-5 h-5 ms-3 rtl:rotate-180" aria-hidden="true"
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                              stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                                    </svg>
                                </label>
                            </li>
                        @endforeach

                    </ul>
                </div>

                @if($this->package->configOptions->isNotEmpty())
                    <hr class="h-px my-6 bg-gray-200 border-0 dark:bg-gray-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Configurable Options</h2>

                    <x-theme::card class="mb-6">
                        @foreach($this->package->configOptions as $option)
                            <div class="mb-4">
                                @if($option->type !== 'radio')
                                    <x-theme::form.label :text="$option->label" for="{{ $option->key }}-input"/>
                                @endif
                                @if($option->type == 'select')
                                    <x-theme::form.select
                                        :options="collect($option->data['options'] ?? [])->pluck('name', 'value')->toArray()"
                                        id="{{ $option->key }}-input"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @elseif($option->type == 'radio')
                                    <x-theme::form.radio-cards
                                        :name="$option->key"
                                        :title="$option->label"
                                        :options="$option->data['options'] ?? []"
                                        :selected="data_get($config_options, $option->key)"
                                        :model="'config_options.'.$option->key"
                                    />
                                @elseif((in_array($option->type, ['text', 'email', 'password'])))
                                    <x-theme::form.input
                                        :type="$option->type"
                                        :name="$option->name"
                                        :placeholder="$option->placeholder"
                                        id="{{ $option->key }}-input"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @elseif(in_array($option->type, ['number', 'range']))
                                    <x-theme::form.input
                                        id="{{ $option->key }}-input"
                                        :type="$option->type"
                                        :name="$option->name"
                                        :placeholder="$option->placeholder"
                                        :min="$option->data['min_value'] ?? ''"
                                        :max="$option->data['max_value'] ?? ''"
                                        :step="$option->data['step_value'] ?? 1"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @elseif($option->type == 'textarea')
                                    <x-theme::form.textarea
                                        :name="$option->name"
                                        :placeholder="$option->placeholder"
                                        id="{{ $option->key }}-input"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @endif
                                @error("config_options.{$option->key}")
                                <x-theme::form.error :text="$message"/>
                                @else
                                    <x-theme::form.description :text="$option->description"/>
                                    @enderror
                            </div>
                        @endforeach
                    </x-theme::card>
                @endif
            </div>

            <div class="mx-auto mt-6 max-w-4xl flex-1 space-y-6 lg:mt-0 lg:w-full">
                <div
                    class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                    <p class="text-xl font-semibold text-gray-900 dark:text-white">Summary</p>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <dl class="flex items-center justify-between gap-4">
                                <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Billing Cycle</dt>
                                <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($this->packagePrice->price) }} / {{ $this->packagePrice->cycle() }}</dd>
                            </dl>

                            <dl class="flex items-center justify-between gap-4">
                                <dt class="text-base font-normal text-gray-500 dark:text-gray-400">Setup Fee</dt>
                                <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($this->packagePrice->setup_fee) }}</dd>
                            </dl>


                        @if($this->calculateConfigOptionCost()['total'] > 0)
                            <p class="text-md font-semibold text-gray-900 dark:text-white">Configurable Options</p>
                            @foreach($this->calculateConfigOptionCost()['breakdown'] as $option)
                                @continue((float) ($option['total'] ?? 0) <= 0)
                                <dl class="flex items-center justify-between gap-4">
                                    <dt class="text-base font-normal text-gray-500 dark:text-gray-400">{{ $option['label'] }}</dt>
                                    <dd class="text-base font-medium text-gray-900 dark:text-white">{{ price($option['total']) }}</dd>
                                </dl>
                                @endforeach
                            @endif
                        </div>

                            <dl class="flex items-start justify-between gap-4 border-t border-gray-200 pt-2 dark:border-gray-700">
                            <dt class="shrink-0 text-base font-bold text-gray-900 dark:text-white">Total</dt>
                            <dd class="min-w-0 text-right text-base font-bold text-gray-900 dark:text-white">{{ price($this->calculateConfigOptionCost()['total'] + $this->packagePrice->price) }} / {{ $this->packagePrice->cycle() }} @if($this->packagePrice->setup_fee > 0) (+ {{ price($this->packagePrice->setup_fee) }} Setup Fee) @endif</dd>
                        </dl>
                    </div>

                    <button type="button" wire:click="addToCart()" wire:loading.attr="disabled"
                            class="flex w-full items-center justify-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">
                        <svg wire:loading aria-hidden="true" role="status"
                             class="inline w-4 h-4 me-3 text-white animate-spin" viewBox="0 0 100 101" fill="none"
                             xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
                                fill="#E5E7EB"/>
                            <path
                                d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
                                fill="currentColor"/>
                        </svg>
                        Add to Cart
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

