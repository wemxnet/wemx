@props([
    'previewUrl' => null,
    'initials' => 'R',
    'inputId' => 'icon',
    'label' => 'Icon',
    'hint' => 'JPEG, PNG, GIF, or WebP. Max 2 MB and 1024×1024 px.',
    'showRemove' => false,
])

<div {{ $attributes->class('flex flex-wrap items-start gap-4') }}>
    <div class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
        <div wire:loading wire:target="icon" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 text-xs text-gray-500 dark:bg-gray-800/80 dark:text-gray-400">
            Uploading…
        </div>
        @if($previewUrl)
            <img src="{{ $previewUrl }}" alt="" class="h-full w-full object-contain">
        @else
            <span class="text-base font-semibold tracking-wide text-primary-700 dark:text-primary-300">{{ $initials }}</span>
        @endif
    </div>
    <div class="min-w-0 flex-1 space-y-2">
        <x-theme::form.label :for="$inputId" :text="$label"/>
        <x-theme::form.file :id="$inputId" wire:model.live="icon" accept="image/jpeg,image/png,image/gif,image/webp"/>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
        @if($showRemove)
            <button
                type="button"
                wire:click="removeIcon"
                wire:confirm="Remove this resource icon?"
                class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"
            >Remove icon</button>
        @endif
        @error('icon') <x-theme::form.error :text="$message"/> @enderror
    </div>
</div>
