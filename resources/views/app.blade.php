<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}"
    class="theme-{{ auth()->check() ? auth()->user()->theme : 'dark' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#07101D">
        <meta name="application-name" content="Airmius">
        <meta name="apple-mobile-web-app-title" content="Airmius">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="manifest" href="{{ route('site.webmanifest') }}?v=5">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=4" type="image/x-icon">
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=4" type="image/x-icon">
        <link rel="icon" href="{{ asset('favicon.png') }}?v=4" type="image/png" sizes="512x512">
        <link rel="icon" href="{{ asset('img/logo/airmius-icon-192.png') }}?v=4" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/logo/airmius-icon-192.png') }}?v=4">
        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
