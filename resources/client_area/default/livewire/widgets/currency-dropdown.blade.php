<?php

use Livewire\Volt\Component;
use App\Models\Currency;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public function setCurrency($currencyId)
    {
        $currency = Currency::find($currencyId);
        if (!$currency) {
            return;
        }

        Session::put('currency', $currencyId);

        $this->redirect((request()->header('Referer', route('dashboard'))), true);
    }
}
?>

<div>
    <button type="button" data-dropdown-toggle="language-dropdown-menu" class="inline-flex h-10 cursor-pointer items-center justify-center rounded-lg px-2 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700 dark:hover:text-white sm:px-3">
        {{ session('currency', settings('currency', 'USD')) }}
    </button>
    <!-- Dropdown -->
    <div class="z-50 my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-lg shadow dark:bg-gray-700 hidden" id="language-dropdown-menu" style="position: absolute; inset: 0px auto auto 0px; margin: 0px; transform: translate(606px, 64px);" aria-hidden="true" data-popper-placement="bottom">
        <ul class="py-2 font-medium" role="none">
            @foreach(Currency::where('is_active', true)->get() as $currency)
            <li>
                <button wire:click="setCurrency('{{ $currency->currency }}')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-white" role="menuitem">
                    <div class="inline-flex items-center">
                        {{ $currency->currency }}
                    </div>
                </button>
            </li>
            @endforeach
        </ul>
    </div>
</div>
