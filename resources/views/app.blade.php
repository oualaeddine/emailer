<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'PageJaunes Mailer') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <meta name="theme-color" content="#FFD400">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PJ Mailer">

    {{-- docs/07-ui-design.md §7.11 — resolve the theme before the bundle loads (same rules as
         resources/js/Theme/ThemeContext.tsx) so dark mode never flashes white. --}}
    <script>
        (function () {
            var org = @json(data_get($page ?? [], 'props.branding.defaultTheme', 'light'));
            var choice = null;
            try { choice = window.localStorage.getItem('pjm.theme'); } catch (e) {}
            var pref = (choice === 'light' || choice === 'dark' || choice === 'system') ? choice : org;
            var dark = pref === 'dark';
            if (pref === 'system') {
                try { dark = window.matchMedia('(prefers-color-scheme: dark)').matches; } catch (e) {}
            }
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
