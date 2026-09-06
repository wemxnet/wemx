@if(isset($order) && auth()->check() && $order->user_id === auth()->id())
    <x-theme::card class="mb-4 p-2">
        <p class="mb-2 px-2 text-sm text-gray-600 dark:text-gray-300">Looking for how-to guides?</p>
        <x-theme::button.primary
            href="{{ route('knowledgebase.index') }}"
            wire:navigate
            class="w-full"
            :text="__('knowledgebase::messages.browse_docs')"
        />
    </x-theme::card>
@endif
