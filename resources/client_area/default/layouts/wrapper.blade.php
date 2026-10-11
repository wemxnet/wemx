<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>@yield('title')</title>
    <link rel="icon" href="@settings('favicon', '/assets/core/img/logo.png')">

    {{-- meta tags --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Meta Description Tag: Affects click-through rates from search results -->
    <meta name="description" content="Manage your orders with an easy-to-use Dashboard">
    <meta name="theme-color" content="#4f46e5">
    <meta name="keywords" content="">

    <!-- Meta Robots Tag: Controls search engine crawling and indexing -->
    <meta name="robots" content="@settings('seo::robots', 'index, follow')">

    <!-- Open Graph Tags: Enhances visibility and engagement on social media platforms -->
    <meta property="og:title" content="{{ trim($__env->yieldContent('title')) }} - @settings('seo::title', 'WemX')">
    <meta property="og:description" content="Manage your orders with an easy-to-use Dashboard">
    <meta property="og:image" content="@settings('seo::image', '/static/wemx.png')">

    <!-- Custom CSS -->
    @vite(['resources/client_area/default/assets/css/app.css','resources/client_area/default/assets/js/app.js'])

    <!-- Livewire Styles -->
    @livewireStyles

    <!-- Custom JS -->
    @yield('header')

    <!-- Dark Mode -->
    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        setTheme();

        document.addEventListener('livewire:navigated', (event) => {
            // On page load or when changing themes, best to add inline in `head` to avoid FOUC
            setTheme();
        });

        function toggleDarkmode() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            }
        }

        function setTheme()
        {
            // On page load or when changing themes, best to add inline in `head` to avoid FOUC
            if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark')
            }
        }
    </script>
</head>

<body class="min-h-screen antialiased bg-gray-50 dark:bg-gray-900 flex flex-col">
@include('theme::layouts.header', ['activePage' => $activePage ?? ''])
    <main class="flex-1 space-y-4 px-3 py-4 dark:bg-gray-900 sm:p-4 lg:p-6">
        @yield('content')
    </main>
@include('theme::layouts.footer')
<script>
    document.addEventListener('livewire:navigated', function () {
        initFlowbite();

        const nav = document.getElementById('client-main-navigation');
        const toggle = document.getElementById('client-nav-toggle');
        if (
            nav &&
            toggle &&
            window.matchMedia('(max-width: 1023px)').matches &&
            !nav.classList.contains('hidden')
        ) {
            toggle.click();
        }
    });
</script>
@livewireScripts
</body>
</html>
