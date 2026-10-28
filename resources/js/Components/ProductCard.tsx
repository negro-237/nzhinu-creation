import { Link, usePage } from '@inertiajs/react';
import { money } from '../lib/money';
import type { ProductCardData, ShopProps } from '../types/shop';

export default function ProductCard({ product, index = 0 }: { product: ProductCardData; index?: number }) {
    const { market, text: t } = usePage<ShopProps>().props.shop;
    return <article data-reveal data-reveal-delay={Math.min(index % 4, 3) * 60} className="group min-w-0">
        <Link href={`/produits/${product.slug}`} className="shop-product-link block" aria-label={`${t.view_product} : ${product.name}`}>
            <div className="relative aspect-[4/5] overflow-hidden border border-black/10 bg-white">
                {product.image ? <img src={product.image.url} alt={product.image.alt} loading="lazy" width="480" height="600" className="h-full w-full object-contain p-4 transition-transform duration-200 motion-safe:group-hover:scale-[1.03]" />
                    : <div className="flex h-full flex-col items-center justify-center gap-4 p-6 text-center"><span className="font-display text-4xl italic text-brand-ink">Nzhinu</span><span className="text-xs text-stone-500">{t.photo_pending}</span></div>}
                <span className="absolute bottom-3 left-3 bg-brand-cream px-3 py-1.5 text-xs text-brand-black">{product.in_stock ? t.in_stock : t.sold_out}</span>
            </div>
            <p className="mt-4 text-xs tracking-widest uppercase text-stone-500">{product.category.name}</p>
            <h3 className="mt-1 break-words font-display text-xl group-hover:text-brand-ink">{product.name}</h3>
            <p className="mt-2 text-sm"><span className="text-stone-600">{t.from} </span><span className="font-medium">{money(product.price_minor, market)}</span></p>
        </Link>
    </article>;
}
