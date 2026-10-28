import { Link, useForm, usePage } from '@inertiajs/react';
import SeoHead from '../Components/SeoHead';
import { useState, type FormEvent } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import ProductGallery from '../Components/ProductGallery';
import ProductCard from '../Components/ProductCard';
import ArrowIcon from '../Components/ArrowIcon';
import { WhatsAppIcon } from '../Components/WhatsAppContact';
import { money } from '../lib/money';
import type { ProductDetail, ShopProps } from '../types/shop';

function ProductContent({ product }: { product: ProductDetail }) {
    const { shop: { market, text: t, whatsapp_url }, seo } = usePage<ShopProps>().props;
    const [variantId, setVariantId] = useState(product.variants[0]?.id);
    const variant = product.variants.find((item) => item.id === variantId)!;
    const form = useForm({ inventory_id: '' as string | number, quantity: 1 });
    const stock = variant.stocks.find((item) => item.id === Number(form.data.inventory_id));
    const available = variant.stocks.some((item) => item.available > 0);
    const maximum = Math.min(stock?.available ?? 99, 99);
    const canOrder = !!stock && stock.available > 0;
    const validQuantity = Number.isInteger(form.data.quantity) && form.data.quantity >= 1;
    const contact = whatsapp_url ? new URL(whatsapp_url) : null;
    contact?.searchParams.set('text', `${t.product_help_message.replace(':name', product.name)}${seo?.canonical ? ` ${seo.canonical}` : ''}`);
    function add(event: FormEvent) { event.preventDefault(); form.post('/panier/lignes'); }
    return <>
        <SeoHead />
        <div className="shop-container pb-8 pt-6 sm:pb-12 sm:pt-8">
            <nav aria-label={t.catalog_breadcrumb} className="mb-7 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs leading-6 text-stone-500 sm:mb-10"><Link href="/" className="py-1 hover:text-brand-ink">{t.home}</Link><span aria-hidden="true">/</span><Link href="/boutique" className="py-1 hover:text-brand-ink">{t.catalog}</Link><span aria-hidden="true">/</span><Link href={`/boutique?category=${encodeURIComponent(product.category.slug)}`} className="py-1 hover:text-brand-ink">{product.category.name}</Link><span aria-hidden="true">/</span><span aria-current="page" className="min-w-0 break-words text-brand-black">{product.name}</span></nav>
            <div className="grid items-start gap-8 lg:grid-cols-[1.08fr_1fr] lg:gap-12 xl:gap-16">
                <div className="min-w-0 lg:col-start-2 lg:row-start-1">
                    <p className="mb-4 flex items-center gap-3 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink"><span className="h-px w-8 bg-brand-ink" aria-hidden="true" />{t.product_eyebrow}</p>
                    <Link href={`/boutique?category=${encodeURIComponent(product.category.slug)}`} className="inline-flex min-h-9 items-center gap-2 rounded-full border border-black/10 bg-white px-3 py-1 text-[10px] tracking-widest uppercase text-brand-ink">{product.category.name}<ArrowIcon /></Link>
                    <h1 className="mt-4 break-words font-display text-4xl leading-tight tracking-tight sm:text-5xl">{product.name}</h1>
                    <p className="mt-3 break-all text-[11px] leading-6 text-stone-500">{t.product_reference} : {variant.sku}</p>
                    <div className="mt-6 flex flex-wrap items-end justify-between gap-4 border-y border-black/10 py-5"><div aria-live="polite" aria-atomic="true"><p className="mb-2 text-[10px] tracking-widest uppercase text-stone-500">{t.product_price}</p><p className="font-display text-3xl sm:text-4xl">{money(variant.price_minor, market)}</p></div><p role="status" className={`flex items-center gap-2 rounded-full border px-3 py-2 text-xs ${available ? 'border-brand-ink/20 bg-brand-gold/10 text-brand-ink' : 'border-black/10 bg-white text-stone-500'}`}><span aria-hidden="true" className={`size-1.5 shrink-0 rounded-full ${available ? 'bg-brand-ink' : 'bg-stone-400'}`} />{available ? t.in_stock : t.sold_out}</p></div>
                </div>
                <div className="min-w-0 lg:col-start-1 lg:row-start-1 lg:row-span-2"><ProductGallery images={product.images} /><div className="mt-5 hidden items-center justify-between gap-4 border-b border-black/10 pb-5 lg:flex"><span className="text-[10px] font-semibold tracking-[0.16em] uppercase text-brand-ink">{t.hero_selection}</span><p className="max-w-56 text-right text-xs leading-6 text-stone-500">{t.product_gallery_note}</p></div></div>
                <div className="min-w-0 lg:col-start-2 lg:row-start-2">
                    <form onSubmit={add} aria-label={t.product_selection} className="border border-black/10 bg-white p-5 sm:p-6">
                        <h2 className="font-display text-xl">{t.product_selection}</h2><p className="mb-5 mt-2 text-xs leading-6 text-stone-500">{t.product_selection_hint}</p>
                        <label htmlFor="product-variant" className="block text-xs font-medium">{t.variant}</label><select id="product-variant" className="shop-field mt-2" value={variantId} disabled={form.processing} onChange={(e) => { setVariantId(Number(e.target.value)); form.setData({ inventory_id: '', quantity: 1 }); form.clearErrors(); }}>
                            {product.variants.map((item) => <option key={item.id} value={item.id}>{[item.label, item.size, item.color].filter(Boolean).join(' · ')}</option>)}
                        </select>
                        <label htmlFor="product-stock" className="mt-5 block text-xs font-medium">{t.stock_location}</label><select id="product-stock" className="shop-field mt-2" required disabled={form.processing || !available} value={form.data.inventory_id} onChange={(e) => { form.setData({ inventory_id: e.target.value, quantity: 1 }); form.clearErrors(); }} aria-invalid={!!form.errors.inventory_id} aria-describedby={form.errors.inventory_id ? 'stock-error' : 'product-stock-hint'}>
                            <option value="">{t.choose_stock}</option>{variant.stocks.map((item) => <option key={item.id} value={item.id} disabled={item.available <= 0}>{item.location} · {item.available} {t.available}</option>)}
                        </select>
                        {form.errors.inventory_id && <p id="stock-error" className="shop-error mt-2" role="alert">{form.errors.inventory_id}</p>}
                        <p id="product-stock-hint" className="mt-2 text-xs leading-6 text-stone-500">{!available ? t.product_unavailable : !stock ? t.product_stock_hint : `${stock.available} ${t.available}`}</p>
                        <div className="mt-5"><label htmlFor="product-quantity" className="block text-xs font-medium">{t.quantity}</label><div className="mt-2 inline-flex items-center border border-black/20">
                            <button type="button" aria-label={t.decrease_quantity} disabled={form.processing || !canOrder || !validQuantity || form.data.quantity <= 1} onClick={() => form.setData('quantity', Math.max(1, form.data.quantity - 1))} className="flex size-11 items-center justify-center text-lg hover:bg-brand-cream disabled:cursor-not-allowed disabled:text-stone-300">−</button>
                            <input id="product-quantity" type="number" className="shop-field !w-20 !border-y-0 !border-x-black/10 !text-center" min={1} max={maximum} required disabled={form.processing || !canOrder} value={form.data.quantity} onChange={(e) => form.setData('quantity', Number(e.target.value))} aria-invalid={!!form.errors.quantity} aria-describedby={form.errors.quantity ? 'quantity-error' : undefined} />
                            <button type="button" aria-label={t.increase_quantity} disabled={form.processing || !canOrder || !validQuantity || form.data.quantity >= maximum} onClick={() => form.setData('quantity', Math.min(maximum, form.data.quantity + 1))} className="flex size-11 items-center justify-center text-lg hover:bg-brand-cream disabled:cursor-not-allowed disabled:text-stone-300">+</button>
                        </div></div>
                        {form.errors.quantity && <p id="quantity-error" className="shop-error" role="alert">{form.errors.quantity}</p>}
                        <button type="submit" disabled={form.processing || !canOrder} className="primary-button mt-6 min-h-12 w-full !py-4">{t.add}<span aria-hidden="true"><ArrowIcon /></span></button>
                        <p className="mt-4 text-[11px] leading-6 text-stone-500">{t.cart_notice}</p>
                    </form>
                    {contact && <a href={contact.toString()} target="_blank" rel="noopener noreferrer" className="mt-4 flex min-h-12 items-center justify-center gap-3 border border-brand-ink/20 px-4 py-3 text-xs text-brand-ink transition-colors hover:border-brand-ink hover:bg-white"><WhatsAppIcon className="size-5 shrink-0" />{t.product_help}<ArrowIcon /></a>}
                </div>
            </div>
            <section aria-labelledby="product-details-title" className="mt-12 grid gap-8 border-t border-black/10 pt-10 sm:mt-16 sm:pt-12 lg:grid-cols-[1.08fr_1fr] lg:gap-12 xl:gap-16">
                <div data-reveal className="min-w-0"><p className="mb-4 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink">{t.product_details_eyebrow}</p><h2 id="product-details-title" className="font-display text-3xl">{t.description}</h2><p className="mt-5 whitespace-pre-line break-words text-sm leading-8 text-stone-600">{product.description || t.product_description_pending}</p>
                    {product.collections.length > 0 && <nav aria-label={t.product_collections} className="mt-7 flex flex-wrap gap-2">{product.collections.map((collection) => <Link key={collection.slug} href={`/boutique?collection=${encodeURIComponent(collection.slug)}`} className="inline-flex min-h-10 items-center gap-2 rounded-full border border-black/10 bg-white px-4 py-2 text-xs hover:border-brand-ink">{collection.name}<ArrowIcon /></Link>)}</nav>}
                </div>
                <div data-reveal className="border-l-2 border-brand-gold bg-brand-black p-6 text-white sm:p-8"><p className="text-[10px] tracking-[0.18em] uppercase text-brand-gold">{t.journey_eyebrow}</p><h2 className="mt-4 font-display text-2xl">{t.product_order_title}</h2><p className="mt-4 text-xs leading-7 text-stone-300">{t.product_order_intro}</p><div className="mt-6 grid gap-5 border-t border-white/15 pt-6">{[['01', t.product_confirmation_title, t.product_confirmation_text], ['02', t.product_fulfillment_title, t.product_fulfillment_text]].map(([number, title, body]) => <div key={number} className="flex items-start gap-4"><span className="font-display text-xl italic text-brand-gold" aria-hidden="true">{number}</span><div><h3 className="text-sm font-medium">{title}</h3><p className="mt-2 text-xs leading-6 text-stone-300">{body}</p></div></div>)}</div></div>
            </section>
            {product.similar_products.length > 0 && <section aria-labelledby="similar-products-title" className="mt-14 border-t border-black/10 pt-10 sm:mt-20 sm:pt-14">
                <div data-reveal className="mb-8 flex flex-wrap items-end justify-between gap-4"><div><p className="mb-3 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink">{t.hero_selection}</p><h2 id="similar-products-title" className="font-display text-3xl sm:text-4xl">{t.similar_products}</h2><p className="mt-3 text-sm leading-7 text-stone-600">{t.similar_products_intro}</p></div><Link href={`/boutique?category=${encodeURIComponent(product.category.slug)}`} className="py-3 text-sm underline underline-offset-8">{t.view_category} <ArrowIcon /></Link></div>
                <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">{product.similar_products.map((item, index) => <ProductCard key={item.id} product={item} index={index} />)}</div>
            </section>}
        </div>
    </>;
}

export default function Product({ product }: { product: ProductDetail }) {
    const { market } = usePage<ShopProps>().props.shop;
    return <ShopLayout><ProductContent key={`${product.id}-${market.code}`} product={product} /></ShopLayout>;
}
