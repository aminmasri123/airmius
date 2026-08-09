<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}"
    class="theme-{{ auth()->check() ? auth()->user()->theme : 'dark' }}"
>
    <head>
        @php
            $seo = \App\Support\SeoMeta::fromInertiaPage($page ?? [], request());
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#07101D">
        <meta name="application-name" content="Airmius">
        <meta name="apple-mobile-web-app-title" content="Airmius">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="manifest" href="{{ route('site.webmanifest') }}?v=8">
        <link rel="shortcut icon" href="{{ asset('img/logo/Airmius-Mark.png') }}?v=8" type="image/png">
        <link rel="icon" href="{{ asset('img/logo/Airmius-Mark.png') }}?v=8" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/logo/Airmius-Mark.png') }}?v=8">
        <title inertia>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}" inertia="description">
        <meta name="robots" content="{{ $seo['robots'] }}" inertia="robots">
        @if ($seo['canonical'])
            <link rel="canonical" href="{{ $seo['canonical'] }}" inertia="canonical">
        @endif
        <meta property="og:site_name" content="{{ $seo['site_name'] }}" inertia="og:site_name">
        <meta property="og:type" content="{{ $seo['type'] }}" inertia="og:type">
        <meta property="og:title" content="{{ $seo['title'] }}" inertia="og:title">
        <meta property="og:description" content="{{ $seo['description'] }}" inertia="og:description">
        @if ($seo['canonical'])
            <meta property="og:url" content="{{ $seo['canonical'] }}" inertia="og:url">
        @endif
        <meta property="og:image" content="{{ $seo['image'] }}" inertia="og:image">
        <meta property="og:locale" content="{{ $seo['locale'] }}" inertia="og:locale">
        <meta name="twitter:card" content="summary_large_image" inertia="twitter:card">
        <meta name="twitter:title" content="{{ $seo['title'] }}" inertia="twitter:title">
        <meta name="twitter:description" content="{{ $seo['description'] }}" inertia="twitter:description">
        <meta name="twitter:image" content="{{ $seo['image'] }}" inertia="twitter:image">
        @if ($seo['schema_json'])
            <script type="application/ld+json" data-seo-schema="airmius-jsonld-schema">{!! $seo['schema_json'] !!}</script>
        @endif

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
        @env('testing')
            @isset($plainTextAssertions)
                <template data-testid="plain-text-assertions">{{ $plainTextAssertions }}</template>
            @endisset
        @endenv
    </body>
</html>
