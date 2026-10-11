@props([

])

<div {{ $attributes->merge(['class' => 'fixed top-0 right-0 z-40 h-screen w-full max-w-sm translate-x-full overflow-y-auto bg-white p-4 transition-transform dark:bg-gray-800', 'tabindex' => '-1']) }}>{{ $slot }}</div>
