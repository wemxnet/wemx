<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <meta name="robots" content="noindex, nofollow"/>

    <title>Install WemX</title>
    <link rel="icon" href="{{ asset(settings('favicon', 'images/favicon.png')) }}">

    <script>
        // Apply the saved (or system) theme before first paint to avoid a flash.
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('tablerTheme'); } catch (e) {}
            if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        })();
    </script>

    <link href="{{ admin_asset('css/tabler.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/tabler-vendors.min.css?1692870487') }}" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ admin_asset('libs/tabler-icons/dist/tabler-icons.min.css') }}">
    <script src="{{ admin_asset('js/tabler.min.js?1692870487') }}" defer></script>

    @include('install::partials.styles')

    @livewireStyles
</head>
<body class="wx-install">

@livewire('install-wizard')

<script>
    function wxToggleTheme() {
        var root = document.documentElement;
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        if (next === 'dark') {
            root.setAttribute('data-bs-theme', 'dark');
        } else {
            root.removeAttribute('data-bs-theme');
        }
        try { localStorage.setItem('tablerTheme', next); } catch (e) {}
    }

    function wxCopy(button) {
        var text = button.parentElement.querySelector('span').innerText.trim();
        var done = function () {
            var icon = button.querySelector('.ti');
            icon.className = 'ti ti-check';
            setTimeout(function () { icon.className = 'ti ti-copy'; }, 1500);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
            return;
        }

        // Fallback for plain-HTTP installs, where the Clipboard API is unavailable.
        var area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(area);
    }
</script>

@livewireScripts
@stack('scripts')
</body>
</html>
