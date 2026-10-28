import { Link, usePage } from '@inertiajs/react';
import { money } from '../lib/money';
import type { ProductCardData, ShopProps } from '../types/shop';
import ArrowIcon from './ArrowIcon';

export default function ProductCard({ product, index = 0 }: { product: ProductCardData; index?: number }) {
    const { market, text: t } = usePage<ShopProps>().props.shop;
    return <article data-reveal data-reveal-delay={Math.min(index % 4, 3) * 60} className="group min-w-0">
        <Link href={`/produits/${product.slug}`} className="shop-product-link flex h-full flex-col border border-black/10 bg-white transition-colors hover:border-brand-ink/40" aria-label={`${t.view_product} : ${product.name}`}>
            <div className="relative aspect-[4/5] overflow-hidden bg-[#f4f1eb]">
                {product.image ? <img src={product.image.url} alt={product.image.alt} loading="lazy" width="480" height="600" className="h-full w-full object-contain p-4 transition-transform duration-200 motion-safe:group-hover:scale-[1.03]" />
                    : <div className="flex h-full flex-col items-center justify-center gap-2 p-3 text-center sm:gap-4 sm:p-6"><span className="font-display text-2xl italic text-brand-ink sm:text-4xl">Nzhinu</span><span className="text-xs text-stone-500">{t.photo_pending}</span></div>}
                <span className="absolute bottom-2 left-2 right-2 flex w-fit max-w-[calc(100%-1rem)] items-center gap-1.5 bg-brand-cream/95 px-2 py-1.5 text-[10px] leading-4 text-brand-black sm:bottom-3 sm:left-3 sm:px-3"><span aria-hidden="true" className={`size-1.5 shrink-0 rounded-full ${product.in_stock ? 'bg-brand-ink' : 'bg-stone-400'}`} />{product.in_stock ? t.in_stock : t.sold_out}</span>
            </div>
            <div className="flex flex-1 flex-col p-3 sm:p-5"><p className="break-words text-[10px] tracking-widest uppercase text-stone-500">{product.category.name}</p>
                <h3 className="mt-2 break-words font-display text-lg leading-snug group-hover:text-brand-ink sm:text-xl">{product.name}</h3>
                <div className="mt-auto flex items-end justify-between gap-2 pt-4"><p className="text-xs leading-6 sm:text-sm"><span className="block text-[10px] text-stone-500">{t.from}</span><span className="font-semibold">{money(product.price_minor, market)}</span></p><span aria-hidden="true" className="flex size-8 shrink-0 items-center justify-center rounded-full border border-black/10 text-brand-ink transition-colors group-hover:border-brand-gold group-hover:bg-brand-gold"><ArrowIcon /></span></div>
            </div>
        </Link>
    </article>;
}
