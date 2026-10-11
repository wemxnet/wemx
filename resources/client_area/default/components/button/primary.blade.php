@props([
    'text' => null,
    'href' => null,
])

<{{ isset($href) ? 'a' : 'button' }} {{ $attributes->class(["inline-flex items-center justify-center focus:outline-none text-white bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"])->merge(['href' => $href]) }}>{{ $text ?? $slot }}</{{ isset($href) ? 'a' : 'button' }}>
