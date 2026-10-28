<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#111111">
        <meta name="csp-nonce" content="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        <link rel="icon" type="image/png" href="{{ url('/images/logo-transparent.png') }}">
        @php($seo = \App\Support\StorefrontSeo::fromPage($page))
        <title inertia="">{{ $seo['title'] }}</title>
        <meta inertia="description" name="description" content="{{ $seo['description'] }}">
        <meta inertia="robots" name="robots" content="{{ $seo['robots'] }}">
        <link inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
        @if ($seo['public'])
            <meta inertia="og:title" property="og:title" content="{{ $seo['title'] }}">
            <meta inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
            <meta inertia="og:url" property="og:url" content="{{ $seo['canonical'] }}">
            <meta inertia="og:type" property="og:type" content="website">
            <meta inertia="og:site_name" property="og:site_name" content="{{ __('shop.brand') }}">
            <meta inertia="og:locale" property="og:locale" content="fr_CM">
            <meta inertia="og:image" property="og:image" content="{{ $seo['image'] }}">
            <meta inertia="og:image:alt" property="og:image:alt" content="{{ $seo['image_alt'] }}">
            <meta inertia="twitter:card" name="twitter:card" content="summary_large_image">
            <meta inertia="twitter:title" name="twitter:title" content="{{ $seo['title'] }}">
            <meta inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
            <meta inertia="twitter:image" name="twitter:image" content="{{ $seo['image'] }}">
            <script inertia="structured-data" type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! $seo['structured_json'] !!}</script>
        @endif
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="bg-stone-50 text-stone-900 antialiased">
        @if ($seo['public'])
            {{-- React createRoot replaces this useful initial HTML on mount; no user-agent-specific rendering. --}}
            <div id="app" data-page="{{ json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}">
                @include('seo.content', ['props' => $page['props'], 'component' => $page['component']])
            </div>
        @else
            @inertia
        @endif
    </body>
</html>
