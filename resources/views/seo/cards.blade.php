<div class="grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">
    @foreach ($items as $item)
        <article class="min-w-0">
            <a href="{{ url('/produits/'.$item['slug']) }}" class="block" aria-label="{{ __('shop.view_product').' : '.$item['name'] }}">
                <div class="flex aspect-[4/5] items-center justify-center border border-black/10 bg-white">
                    @if ($item['image'])
                        <img src="{{ $item['image']['url'] }}" alt="{{ $item['image']['alt'] }}" width="480" height="600" loading="lazy" class="h-full w-full object-contain p-4">
                    @else
                        <span class="font-display text-3xl italic text-brand-ink">Nzhinu</span>
                    @endif
                </div>
                <p class="mt-4 text-xs text-stone-500">{{ $item['category']['name'] }}</p>
                <h3 class="mt-1 break-words font-display text-xl">{{ $item['name'] }}</h3>
                <p class="mt-2 text-sm">{{ __('shop.from') }} {{ number_format($item['price_minor'] / ($shop['market']['currency'] === 'CAD' ? 100 : 1), $shop['market']['currency'] === 'CAD' ? 2 : 0, ',', "\u{202F}") }}{{ $shop['market']['currency'] === 'CAD' ? ' $' : '' }}</p>
                <p class="mt-2 text-xs text-stone-600">{{ $item['in_stock'] ? __('shop.in_stock') : __('shop.sold_out') }}</p>
            </a>
        </article>
    @endforeach
</div>
