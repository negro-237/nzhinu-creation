import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import ProductGallery from '../Components/ProductGallery';
import ProductCard from '../Components/ProductCard';
import { money } from '../lib/money';
import type { ProductDetail, ShopProps } from '../types/shop';

function ProductContent({ product }: { product: ProductDetail }) {
    const { market, text: t } = usePage<ShopProps>().props.shop;
    const [variantId, setVariantId] = useState(product.variants[0]?.id);
    const variant = product.variants.find((item) => item.id === variantId)!;
    const form = useForm({ inventory_id: '' as string | number, quantity: 1 });
    const stock = variant.stocks.find((item) => item.id === Number(form.data.inventory_id));
    function add(event: FormEvent) { event.preventDefault(); form.post('/panier/lignes'); }
    return <>
        <Head title={product.name}>
            <meta head-key="description" name="description" content={product.meta_description} /><meta head-key="og:title" property="og:title" content={product.name} />
            <meta head-key="og:description" property="og:description" content={product.meta_description} />
            {product.images[0] && <meta head-key="og:image" property="og:image" content={product.images[0].url} />}
        </Head>
        <div className="shop-container py-8 sm:py-12">
            <nav aria-label={t.home} className="mb-8 flex flex-wrap gap-2 text-xs text-stone-600"><Link href="/">{t.home}</Link><span aria-hidden="true">/</span><Link href="/boutique">{t.catalog}</Link><span aria-hidden="true">/</span><span aria-current="page">{product.name}</span></nav>
            <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
                <ProductGallery images={product.images} />
                <div>
                    <Link href={`/boutique?category=${product.category.slug}`} className="text-xs tracking-[0.2em] uppercase text-brand-ink">{product.category.name}</Link>
                    <h1 className="mt-4 break-words font-display text-4xl leading-tight sm:text-5xl">{product.name}</h1>
                    <p className="mt-6 text-2xl font-medium">{money(variant.price_minor, market)}</p>
                    <p className="mt-3 text-sm text-stone-600">{variant.stocks.some((item) => item.available > 0) ? t.in_stock : t.sold_out}</p>
                    <form onSubmit={add} className="mt-8 space-y-5 border-y border-black/10 py-7">
                        <label className="block text-sm">{t.variant}<select className="shop-field mt-2" value={variantId} onChange={(e) => { setVariantId(Number(e.target.value)); form.setData('inventory_id', ''); form.setData('quantity', 1); form.clearErrors(); }}>
                            {product.variants.map((item) => <option key={item.id} value={item.id}>{[item.label, item.size, item.color].filter(Boolean).join(' · ')}</option>)}
                        </select></label>
                        <label className="block text-sm">{t.stock_location}<select className="shop-field mt-2" required value={form.data.inventory_id} onChange={(e) => form.setData('inventory_id', e.target.value)} aria-invalid={!!form.errors.inventory_id} aria-describedby={form.errors.inventory_id ? 'stock-error' : undefined}>
                            <option value="">{t.choose_stock}</option>{variant.stocks.map((item) => <option key={item.id} value={item.id} disabled={item.available <= 0}>{item.location} · {item.available} {t.available}</option>)}
                        </select></label>
                        {form.errors.inventory_id && <p id="stock-error" className="shop-error" role="alert">{form.errors.inventory_id}</p>}
                        <label className="block text-sm">{t.quantity}<input type="number" className="shop-field mt-2 !w-28" min={1} max={Math.min(stock?.available ?? 99, 99)} required value={form.data.quantity} onChange={(e) => form.setData('quantity', Number(e.target.value))} aria-invalid={!!form.errors.quantity} aria-describedby={form.errors.quantity ? 'quantity-error' : undefined} /></label>
                        {form.errors.quantity && <p id="quantity-error" className="shop-error" role="alert">{form.errors.quantity}</p>}
                        <button type="submit" disabled={form.processing || !stock || stock.available <= 0} className="primary-button w-full">{t.add}<span aria-hidden="true">↗</span></button>
                        <p className="text-xs leading-6 text-stone-600">{t.cart_notice}</p>
                    </form>
                    <h2 className="mt-8 font-display text-2xl">{t.description}</h2>
                    {product.description && <p className="mt-4 whitespace-pre-line text-sm leading-8 text-stone-600">{product.description}</p>}
                    <div className="mt-5 flex flex-wrap gap-2">{product.collections.map((collection) => <Link key={collection.slug} href={`/boutique?collection=${collection.slug}`} className="border border-black/10 bg-white px-3 py-2 text-xs">{collection.name}</Link>)}</div>
                </div>
            </div>
            {product.similar_products.length > 0 && <section aria-labelledby="similar-products-title" className="mt-14 border-t border-black/10 pt-10 sm:mt-20 sm:pt-14">
                <div data-reveal className="mb-8 flex flex-wrap items-end justify-between gap-4"><div><h2 id="similar-products-title" className="font-display text-3xl sm:text-4xl">{t.similar_products}</h2><p className="mt-3 text-sm leading-7 text-stone-600">{t.similar_products_intro}</p></div><Link href={`/boutique?category=${encodeURIComponent(product.category.slug)}`} className="text-sm underline underline-offset-8">{t.view_category} ↗</Link></div>
                <div className="grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">{product.similar_products.map((item, index) => <ProductCard key={item.id} product={item} index={index} />)}</div>
            </section>}
        </div>
    </>;
}

export default function Product({ product }: { product: ProductDetail }) {
    const { market } = usePage<ShopProps>().props.shop;
    return <ShopLayout><ProductContent key={`${product.id}-${market.code}`} product={product} /></ShopLayout>;
}
