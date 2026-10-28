@php($shop = $props['shop'])
<div class="min-h-screen bg-brand-cream text-brand-black">
    <header class="shop-container flex flex-wrap items-center justify-between gap-5 border-b border-black/10 py-5">
        <a href="{{ url('/') }}" class="flex items-center gap-3"><img src="{{ url('/images/logo-transparent.png') }}" alt="" width="64" height="64" class="size-16 object-contain"><span class="text-sm font-semibold tracking-widest uppercase">{{ __('shop.brand') }}</span></a>
        <nav aria-label="{{ __('shop.nav') }}" class="flex gap-6 text-sm"><a href="{{ url('/boutique') }}">{{ __('shop.catalog') }}</a><a href="{{ url('/panier') }}">{{ __('shop.cart') }}</a></nav>
    </header>
    <main class="shop-container py-10 sm:py-14">
        @if ($component === 'Welcome')
            <section class="grid items-center gap-10 pb-12 lg:grid-cols-2">
                <div><h1 class="font-display text-5xl leading-tight">{{ $props['content']['title'] }} <span class="italic text-brand-ink">{{ $props['content']['title_accent'] }}</span></h1><p class="mt-6 text-base leading-8 text-stone-600">{{ $props['content']['intro'] }}</p><a href="{{ url('/boutique') }}" class="primary-button mt-7">{{ $props['content']['discover'] }}</a></div>
                @php($cover = collect($props['featured'])->first(fn ($item) => $item['image']))
                @if ($cover)<a href="{{ url('/produits/'.$cover['slug']) }}"><img src="{{ $cover['image']['url'] }}" alt="{{ $cover['image']['alt'] }}" width="640" height="800" fetchpriority="high" class="mx-auto aspect-[4/5] w-full max-w-lg object-cover"></a>@else<img src="{{ url('/images/logo-transparent.png') }}" alt="{{ $props['content']['logo_alt'] }}" width="1280" height="1280" fetchpriority="high" class="mx-auto aspect-square w-full max-w-lg object-contain">@endif
            </section>
            <section aria-labelledby="initial-featured"><h2 id="initial-featured" class="mb-8 font-display text-3xl">{{ __('shop.featured') }}</h2>@include('seo.cards', ['items' => $props['featured']])</section>
            @if ($props['categories'])
                <section class="mt-12"><h2 class="mb-6 font-display text-3xl">{{ __('shop.our_categories') }}</h2><ul class="grid gap-5 sm:grid-cols-3">@foreach ($props['categories'] as $category)<li class="border border-black/10 bg-white p-6"><a href="{{ url('/boutique').'?category='.rawurlencode($category['slug']) }}"><h3 class="font-display text-2xl">{{ $category['name'] }}</h3>@if ($category['description'])<p class="mt-3 whitespace-pre-line text-sm leading-7">{{ $category['description'] }}</p>@endif</a></li>@endforeach</ul></section>
            @endif
            <section class="mt-12 border-t border-black/10 pt-8"><h2 class="font-display text-3xl">{{ __('shop.journey_title') }}</h2><ol class="mt-8 grid gap-6 lg:grid-cols-3">@foreach ([1, 2, 3] as $step)<li><h3 class="font-display text-xl">{{ __('shop.journey_'.$step.'_title') }}</h3><p class="mt-3 text-sm leading-7">{{ __('shop.journey_'.$step.'_text') }}</p></li>@endforeach</ol></section>
        @elseif ($component === 'Catalog')
            <h1 class="font-display text-4xl">{{ $seo['category']['name'] ?? __('shop.catalog') }}</h1><p class="mt-5 max-w-xl text-sm leading-7">{{ $seo['category']['description'] ?? __('shop.catalog_intro') }}</p>
            <nav aria-label="{{ __('shop.all_categories') }}" class="my-8 flex flex-wrap gap-4">@foreach ($props['categories'] as $category)<a href="{{ url('/boutique').'?category='.rawurlencode($category['slug']) }}" class="underline underline-offset-4">{{ $category['name'] }}</a>@endforeach</nav>
            @include('seo.cards', ['items' => $props['products']['items']])
            @if (! $props['products']['items'])<div class="py-8"><h2 class="font-display text-2xl">{{ __('shop.catalog_empty_title') }}</h2><p class="mt-4">{{ __('shop.catalog_empty_text') }}</p><a href="{{ url('/boutique') }}" class="primary-button mt-6">{{ __('shop.catalog_all') }}</a></div>@endif
            <nav aria-label="Pagination" class="mt-10 flex justify-between gap-4">@if ($props['products']['previous_url'])<a href="{{ $props['products']['previous_url'] }}" rel="prev">{{ __('shop.previous') }}</a>@endif @if ($props['products']['next_url'])<a href="{{ $props['products']['next_url'] }}" rel="next">{{ __('shop.next') }}</a>@endif</nav>
        @elseif ($component === 'Product')
            @php($product = $props['product'])
            <nav aria-label="{{ __('shop.home') }}" class="mb-8 flex flex-wrap gap-3 text-xs"><a href="{{ url('/') }}">{{ __('shop.home') }}</a><a href="{{ url('/boutique') }}">{{ __('shop.catalog') }}</a><a href="{{ url('/boutique').'?category='.rawurlencode($product['category']['slug']) }}">{{ $product['category']['name'] }}</a></nav>
            <div class="grid gap-10 lg:grid-cols-2">
                <div>@if ($product['images'])<img src="{{ $product['images'][0]['url'] }}" alt="{{ $product['images'][0]['alt'] }}" width="640" height="800" fetchpriority="high" class="aspect-[4/5] w-full object-contain">@else<p class="flex aspect-[4/5] items-center justify-center border border-black/10 bg-white font-display text-4xl italic text-brand-ink">Nzhinu</p>@endif</div>
                <div><h1 class="font-display text-4xl">{{ $product['name'] }}</h1><p class="mt-6 text-2xl">{{ number_format($product['variants'][0]['price_minor'] / ($shop['market']['currency'] === 'CAD' ? 100 : 1), $shop['market']['currency'] === 'CAD' ? 2 : 0, ',', "\u{202F}") }}{{ $shop['market']['currency'] === 'CAD' ? ' $' : '' }}</p><p class="mt-4 text-sm">{{ $product['in_stock'] ? __('shop.in_stock') : __('shop.sold_out') }}</p><h2 class="mt-8 font-display text-2xl">{{ __('shop.description') }}</h2><p class="mt-4 whitespace-pre-line text-sm leading-8">{{ $product['description'] }}</p></div>
            </div>
            @if ($product['similar_products'])<section class="mt-12"><h2 class="mb-8 font-display text-3xl">{{ __('shop.similar_products') }}</h2>@include('seo.cards', ['items' => $product['similar_products']])</section>@endif
        @elseif ($component === 'ContentPage')
            <article class="mx-auto max-w-3xl"><h1 class="font-display text-4xl">{{ $props['page']['title'] }}</h1><div class="mt-8 whitespace-pre-line break-words text-base leading-8">{{ $props['page']['body'] }}</div></article>
        @endif
        <p data-interactive-notice class="mt-8 border-l-2 border-brand-gold bg-white p-4 text-sm leading-7">{{ __('seo.javascript_notice') }}</p>
    </main>
    <footer class="border-t border-black/10 bg-brand-cream py-10"><div class="shop-container"><p class="font-semibold">{{ __('shop.brand') }}</p><address class="mt-4 text-sm not-italic">{{ $shop['boutique']['address'] }}</address>@if ($shop['contact_email'])<a class="mt-4 inline-block text-sm underline" href="mailto:{{ $shop['contact_email'] }}">{{ $shop['contact_email'] }}</a>@endif @if ($shop['whatsapp_url'])<a class="ml-4 inline-block text-sm underline" href="{{ $shop['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('shop.whatsapp') }}</a>@endif<nav class="mt-5 flex flex-wrap gap-5 text-sm">@foreach ($shop['pages'] as $link)<a href="{{ url('/pages/'.$link['slug']) }}">{{ $link['title'] }}</a>@endforeach @foreach ($shop['social_links'] as $social)<a href="{{ $social['url'] }}" rel="noopener noreferrer">{{ $social['label'] }}</a>@endforeach</nav></div></footer>
</div>
