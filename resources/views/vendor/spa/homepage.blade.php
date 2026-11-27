<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="16x16" href="/storage/images/schooltool/favicon-16.png">
    <link rel="icon" type="image/svg+xml" href="/storage/images/schooltool/schooltool-mark.svg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=roboto:100,300,400,500,700,900" rel="stylesheet" />

    <title>Spa Homepage</title>
    <script>
        window.schooltoolEchoEnvironment = {{ Illuminate\Support\Js::from(config('frontend.echo')) }};
    </script>
    @if (!app()->runningUnitTests())
    @vite('resources/js/apps/homepage.js')
    @endif
</head>

<body class="antialiased">

    <div id="app">
        @include('homepage.startup')
    </div>

</body>

</html>
