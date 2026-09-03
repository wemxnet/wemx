@if(isset($order) && auth()->check() && $order->user_id === auth()->id())
    <x-theme::card class="mb-4 p-2">
        <p class="mb-2 px-2 text-sm text-gray-600 dark:text-gray-300">Need help with this order?</p>
        <x-theme::button.primary
            href="{{ route('tickets.create', ['order' => $order->id]) }}"
            wire:navigate
            class="w-full"
            :text="__('tickets::messages.get_help')"
        />
    </x-theme::card>
@endif
