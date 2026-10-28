<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#111111">
        @php($seo = \App\Support\StorefrontSeo::fromPage($page))
        <title inertia="">{{ $seo['title'] }}</title>
        <meta inertia="description" name="description" content="{{ $seo['description'] }}">
        <meta inertia="robots" name="robots" content="{{ $seo['robots'] }}">
        <link inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
        @if ($seo['product'])
            <meta inertia="og:title" property="og:title" content="{{ $seo['product']['name'] }}">
            <meta inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
            @if (!empty($seo['product']['images'][0]))
                <meta inertia="og:image" property="og:image" content="{{ $seo['product']['images'][0]['url'] }}">
            @endif
        @endif
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="bg-stone-50 text-stone-900 antialiased">
        @inertia
    </body>
</html>
