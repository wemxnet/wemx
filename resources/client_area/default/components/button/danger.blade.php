@props([
    'text' => null,
    'href' => null,
])

<{{ isset($href) ? 'a' : 'button' }} {{ $attributes->class(["inline-flex items-center justify-center focus:outline-none text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-800"])->merge(['href' => $href]) }}>{{ $text ?? $slot }}</{{ isset($href) ? 'a' : 'button' }}>
