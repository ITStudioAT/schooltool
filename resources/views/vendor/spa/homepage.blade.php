<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/storage/images/favicon.ico">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=roboto:100,300,400,500,700,900" rel="stylesheet" />

    <title>Spa Homepage</title>
    @if (!app()->runningUnitTests())
    @vite('resources/js/apps/homepage.js')
    @endif
</head>

<body class="antialiased">

    <div id="app">
        <div id="app-preloader" style="display:flex;align-items:center;justify-content:center;height:100vh;background:#f5f5f5;">
            <div style="display:flex;gap:10px;align-items:center;">
                <span style="width:14px;height:14px;border-radius:50%;background:#f39200;animation:app-bounce 1.4s infinite ease-in-out 0s;"></span>
                <span style="width:14px;height:14px;border-radius:50%;background:#3aaa35;animation:app-bounce 1.4s infinite ease-in-out 0.16s;"></span>
                <span style="width:14px;height:14px;border-radius:50%;background:#37474f;animation:app-bounce 1.4s infinite ease-in-out 0.32s;"></span>
            </div>
            <style>
                @keyframes app-bounce {
                    0%, 100% { transform: translateY(0) scale(1); opacity: 1; }
                    25% { transform: translateY(-12px) scale(1.15); opacity: 1; }
                    50% { transform: translateY(0) scale(1); opacity: 0.7; }
                    75% { transform: translateY(2px) scale(0.95); opacity: 0.85; }
                }
            </style>
        </div>
    </div>

</body>

</html>