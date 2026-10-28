import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import ProductCard from '../Components/ProductCard';
import type { ProductCardData, ShopProps, Taxonomy } from '../types/shop';

type Filters = { q?: string; category?: string; collection?: string; min_price?: string; max_price?: string; sort?: string; in_stock?: boolean | string };
type Props = {
    products: { items: ProductCardData[]; current_page: number; last_page: number; total: number; previous_url: string | null; next_url: string | null };
    filters: Filters; categories: Taxonomy[]; collections: Taxonomy[];
};

export default function Catalog({ products, filters, categories, collections }: Props) {
    const { text: t } = usePage<ShopProps>().props.shop;
    const [showFilters, setShowFilters] = useState(false);
    const form = useForm({ q: filters.q ?? '', category: filters.category ?? '', collection: filters.collection ?? '',
        min_price: filters.min_price ?? '', max_price: filters.max_price ?? '', sort: filters.sort ?? 'newest',
        in_stock: filters.in_stock === true || filters.in_stock === '1' });
    function search(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => Object.fromEntries(Object.entries({ ...data, in_stock: data.in_stock ? '1' : '' }).filter(([, value]) => value !== '')));
        form.get('/boutique', { preserveState: true, preserveScroll: true });
    }
    return <ShopLayout>
        <Head title={t.catalog}>
            <meta head-key="description" name="description" content={t.catalog_intro} />
            {Object.keys(filters).some((key) => key !== 'page') && <meta head-key="robots" name="robots" content="noindex,follow" />}
        </Head>
        <div className="shop-container py-10 sm:py-14">

            <h1 className="font-display text-4xl sm:text-5xl">{t.catalog}</h1>
            <p className="mt-4 max-w-xl text-sm leading-7 text-stone-600">{t.catalog_intro}</p>
            <div className="mt-10 grid gap-10 lg:grid-cols-[240px_minmax(0,1fr)]">
                <aside>
                    <button type="button" className="secondary-button mb-3 w-full gap-3 lg:hidden" aria-expanded={showFilters} aria-controls="catalog-filters" onClick={() => setShowFilters(!showFilters)}>{t.filters}<span aria-hidden="true">{showFilters ? '−' : '+'}</span></button>
                    <form id="catalog-filters" onSubmit={search} className={`shop-feedback ${showFilters ? 'block' : 'hidden'} space-y-5 border border-black/10 bg-white p-5 lg:block`} aria-label={t.apply}>
                        <label className="block text-sm">{t.search}<input type="search" className="shop-field mt-2" placeholder={t.search_placeholder} value={form.data.q} onChange={(e) => form.setData('q', e.target.value)} maxLength={100} /></label>
                        {form.errors.q && <p role="alert" className="shop-error">{form.errors.q}</p>}
                        <label className="block text-sm">{t.category}<select className="shop-field mt-2" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}><option value="">{t.all_categories}</option>{categories.map((item) => <option key={item.slug} value={item.slug}>{item.name}</option>)}</select></label>
                        <label className="block text-sm">{t.collection}<select className="shop-field mt-2" value={form.data.collection} onChange={(e) => form.setData('collection', e.target.value)}><option value="">{t.all_collections}</option>{collections.map((item) => <option key={item.slug} value={item.slug}>{item.name}</option>)}</select></label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="text-xs">{t.min_price}<input className="shop-field mt-2" inputMode="decimal" value={form.data.min_price} onChange={(e) => form.setData('min_price', e.target.value)} maxLength={20} /></label>
                            <label className="text-xs">{t.max_price}<input className="shop-field mt-2" inputMode="decimal" value={form.data.max_price} onChange={(e) => form.setData('max_price', e.target.value)} maxLength={20} /></label>
                        </div>
                        {(form.errors.min_price || form.errors.max_price) && <p className="shop-error" role="alert">{form.errors.min_price ?? form.errors.max_price}</p>}
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-brand-black" checked={form.data.in_stock} onChange={(e) => form.setData('in_stock', e.target.checked)} />{t.in_stock_only}</label>
                        <label className="block text-sm">{t.sort}<select className="shop-field mt-2" value={form.data.sort} onChange={(e) => form.setData('sort', e.target.value)}>{['newest', 'price_asc', 'price_desc', 'name'].map((sort) => <option key={sort} value={sort}>{t[sort]}</option>)}</select></label>
                        <button type="submit" className="primary-button w-full" disabled={form.processing}>{t.apply}</button>
                        <Link href="/boutique" className="block text-center text-xs underline underline-offset-4">{t.reset}</Link>
                    </form>
                </aside>
                <section aria-label={t.results}>
                    <p className="mb-6 text-sm text-stone-600" role="status">{products.total} {t.results}</p>
                    {products.items.length ? <div className="grid grid-cols-2 gap-x-4 gap-y-9 xl:grid-cols-3 sm:gap-x-6">{products.items.map((product, index) => <ProductCard key={product.id} product={product} index={index} />)}</div>
                        : <div className="border border-dashed border-black/20 p-8 text-center sm:p-16"><h2 className="font-display text-2xl">{t.empty_catalog}</h2><p className="mt-4 text-sm leading-7 text-stone-600">{t.empty_catalog_text}</p><Link href="/boutique" className="secondary-button mt-6">{t.reset}</Link></div>}
                    {products.last_page > 1 && <nav aria-label="Pagination" className="mt-12 flex flex-wrap items-center justify-between gap-4 border-t border-black/10 pt-6">
                        {products.previous_url ? <Link href={products.previous_url} className="secondary-button">{t.previous}</Link> : <span />}
                        <span className="text-xs text-stone-600">{t.page} {products.current_page} {t.of} {products.last_page}</span>
                        {products.next_url ? <Link href={products.next_url} className="secondary-button">{t.next}</Link> : <span />}
                    </nav>}
                </section>
            </div>
        </div>
    </ShopLayout>;
}
