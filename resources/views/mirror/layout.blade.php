<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title')</title>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; background: #f4f5f7; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1f2937; display: flex; flex-direction: column; }
        .mirror-bar { flex: 0 0 auto; padding: 10px 16px; background: #ffffff; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .mirror-bar small { display: block; color: #6b7280; font-size: 12px; }
        .mirror-bar strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mirror-frame { flex: 1 1 auto; width: 100%; max-width: 800px; margin: 16px auto; border: 0; background: #ffffff; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
        @media (max-width: 832px) { .mirror-frame { margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
