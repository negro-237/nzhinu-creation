import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import SeoHead from '../Components/SeoHead';
import ShopLayout from '../Layouts/ShopLayout';
import ProductCard from '../Components/ProductCard';
import CategoryIcon from '../Components/CategoryIcon';
import ArrowIcon from '../Components/ArrowIcon';
import { WhatsAppIcon } from '../Components/WhatsAppContact';
import type { ProductCardData, ShopProps, Taxonomy } from '../types/shop';

type Filters = { q?: string; category?: string; collection?: string; min_price?: string; max_price?: string; sort?: string; in_stock?: boolean | string };
type Props = {
    products: { items: ProductCardData[]; current_page: number; last_page: number; total: number; previous_url: string | null; next_url: string | null };
    filters: Filters; categories: Taxonomy[]; collections: Taxonomy[];
};

function filterValues(filters: Filters) {
    return { q: filters.q ?? '', category: filters.category ?? '', collection: filters.collection ?? '',
        min_price: filters.min_price ?? '', max_price: filters.max_price ?? '', sort: filters.sort ?? 'newest',
        in_stock: filters.in_stock === true || filters.in_stock === '1' };
}

function queryValues(data: ReturnType<typeof filterValues>) {
    return Object.fromEntries(Object.entries({ ...data, in_stock: data.in_stock ? '1' : '' })
        .filter(([key, value]) => value !== '' && !(key === 'sort' && value === 'newest')));
}

function FilterIcon() {
    return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-5 shrink-0" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18M7 3v6m10 0v6M9 15v6" /></svg>;
}

export default function Catalog({ products, filters, categories, collections }: Props) {
    const { shop: { text: t, categories: menuCategories, whatsapp_url }, seo } = usePage<ShopProps>().props;
    const [showFilters, setShowFilters] = useState(false);
    const form = useForm(filterValues(filters));
    // Shortcuts, chips and browser history update both the results and the visible form.
    useEffect(() => { form.setData(filterValues(filters)); },
        [filters.q, filters.category, filters.collection, filters.min_price, filters.max_price, filters.sort, filters.in_stock]);

    function apply(sort?: string) {
        form.transform((data) => queryValues({ ...data, sort: sort ?? data.sort }));
        form.get('/boutique', { preserveState: true, preserveScroll: true });
    }
    function search(event: FormEvent) { event.preventDefault(); apply(); }
    function href(changes: Partial<Filters>) {
        const query = new URLSearchParams(queryValues(filterValues({ ...filters, ...changes })));
        return `/boutique${query.size ? `?${query}` : ''}`;
    }
    const chips = [
        { key: 'q', label: filters.q ? `${t.search} : ${filters.q}` : '' },
        { key: 'category', label: filters.category ? categories.find((item) => item.slug === filters.category)?.name ?? filters.category : '' },
        { key: 'collection', label: filters.collection ? collections.find((item) => item.slug === filters.collection)?.name ?? filters.collection : '' },
        { key: 'min_price', label: filters.min_price ? `${t.min_price} : ${filters.min_price}` : '' },
        { key: 'max_price', label: filters.max_price ? `${t.max_price} : ${filters.max_price}` : '' },
        { key: 'in_stock', label: filterValues(filters).in_stock ? t.in_stock_only : '' },
    ].filter((chip) => chip.label);

    return <ShopLayout>
        <SeoHead />
        <section className="border-b border-black/10" aria-labelledby="catalog-title">
            <div className="shop-container pb-8 pt-6 sm:pb-10 sm:pt-8">
                <nav aria-label={t.catalog_breadcrumb} className="mb-5 flex items-center gap-3 text-xs text-stone-500 sm:mb-8"><Link href="/" className="py-2 hover:text-brand-ink">{t.home}</Link><span aria-hidden="true">/</span><span aria-current="page">{seo?.category?.name ?? t.catalog}</span></nav>
                <div className="grid items-end gap-5 sm:gap-7 lg:grid-cols-[1.5fr_1fr] lg:gap-16">
                    <div data-reveal><p className="mb-4 flex items-center gap-3 text-[10px] font-semibold tracking-[0.2em] uppercase text-brand-ink"><span className="h-px w-8 bg-brand-ink" aria-hidden="true" />{t.catalog_eyebrow}</p><h1 id="catalog-title" className="break-words font-display text-4xl tracking-tight sm:text-6xl">{seo?.category?.name ?? t.catalog}</h1><p className="mt-5 max-w-xl text-sm leading-7 text-stone-600">{seo?.category?.description || t.catalog_intro}</p></div>
                    <div data-reveal data-reveal-delay="100" className="border-l-2 border-brand-gold bg-brand-black p-5 text-white sm:p-7"><p className="hidden text-[10px] tracking-[0.18em] uppercase text-brand-gold sm:block">{t.hero_selection}</p><p className="font-display text-xl italic sm:mt-3 sm:text-2xl">{t.catalog_signature}</p><p className="mt-3 hidden max-w-sm text-xs leading-6 text-stone-300 sm:block">{t.catalog_service}</p></div>
                </div>
                {categories.length > 0 && <nav aria-label={t.catalog_categories} className="mt-8"><ul className="flex flex-wrap gap-2">
                    <li><Link href={href({ category: '' })} preserveState preserveScroll aria-current={!filters.category ? 'page' : undefined} className={`inline-flex min-h-11 items-center rounded-full border px-5 text-xs font-medium transition-colors ${!filters.category ? 'border-brand-black bg-brand-black text-brand-gold' : 'border-black/15 bg-white hover:border-brand-ink'}`}>{t.catalog_all}</Link></li>
                    {categories.map((category) => <li key={category.slug}><Link href={href({ category: category.slug })} preserveState preserveScroll aria-current={filters.category === category.slug ? 'page' : undefined} className={`inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-xs font-medium transition-colors ${filters.category === category.slug ? 'border-brand-black bg-brand-black text-brand-gold' : 'border-black/15 bg-white hover:border-brand-ink'}`}><span className="flex size-6 items-center justify-center [&_svg]:size-6" aria-hidden="true"><CategoryIcon icon={menuCategories.find((item) => item.slug === category.slug)?.icon ?? 'grid'} /></span>{category.name}</Link></li>)}
                </ul></nav>}
            </div>
        </section>
        <div className="shop-container py-8 sm:py-12">
            <div className="grid items-start gap-7 lg:grid-cols-[260px_minmax(0,1fr)] lg:gap-10">
                <aside aria-label={t.refine_selection}>
                    <button type="button" className="secondary-button mb-3 w-full justify-between gap-3 !border-brand-black !bg-brand-black !text-white lg:hidden" aria-label={t.filters} aria-expanded={showFilters} aria-controls="catalog-filters" onClick={() => setShowFilters(!showFilters)}><span className="flex items-center gap-3"><FilterIcon />{t.filters}{chips.length > 0 && <span className="rounded-full bg-brand-gold px-2 py-0.5 text-xs text-brand-black">{chips.length}</span>}</span><span aria-hidden="true">{showFilters ? '−' : '+'}</span></button>
                    <form id="catalog-filters" onSubmit={search} className={`shop-feedback ${showFilters ? 'block' : 'hidden'} border border-black/10 bg-white lg:block`} aria-label={t.apply}>
                        <div className="border-b border-black/10 p-5"><h2 className="flex items-center gap-3 font-display text-xl"><span className="text-brand-ink"><FilterIcon /></span>{t.refine_selection}</h2><p className="mt-2 text-xs leading-6 text-stone-500">{t.filter_hint}</p></div>
                        <div className="space-y-5 p-5">
                            <label className="block text-xs font-medium">{t.search}<input type="search" className="shop-field mt-2 !bg-brand-cream" placeholder={t.search_placeholder} value={form.data.q} onChange={(e) => form.setData('q', e.target.value)} maxLength={100} /></label>
                            {form.errors.q && <p role="alert" className="shop-error">{form.errors.q}</p>}
                            <label className="block text-xs font-medium">{t.category}<select className="shop-field mt-2" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}><option value="">{t.all_categories}</option>{categories.map((item) => <option key={item.slug} value={item.slug}>{item.name}</option>)}</select></label>
                            <label className="block text-xs font-medium">{t.collection}<select className="shop-field mt-2" value={form.data.collection} onChange={(e) => form.setData('collection', e.target.value)}><option value="">{t.all_collections}</option>{collections.map((item) => <option key={item.slug} value={item.slug}>{item.name}</option>)}</select></label>
                            <fieldset className="border-t border-black/10 pt-4"><legend className="px-1 text-xs font-medium">{t.price_range}</legend><div className="grid grid-cols-2 gap-3">
                                <label className="text-[11px] text-stone-600">{t.min_price}<input className="shop-field mt-2" inputMode="decimal" value={form.data.min_price} onChange={(e) => form.setData('min_price', e.target.value)} maxLength={20} /></label>
                                <label className="text-[11px] text-stone-600">{t.max_price}<input className="shop-field mt-2" inputMode="decimal" value={form.data.max_price} onChange={(e) => form.setData('max_price', e.target.value)} maxLength={20} /></label>
                            </div></fieldset>
                            {(form.errors.min_price || form.errors.max_price) && <p className="shop-error" role="alert">{form.errors.min_price ?? form.errors.max_price}</p>}
                            <label className="flex min-h-11 items-center gap-3 text-xs"><input type="checkbox" className="size-4 accent-brand-ink" checked={form.data.in_stock} onChange={(e) => form.setData('in_stock', e.target.checked)} />{t.in_stock_only}</label>
                            <button type="submit" className="primary-button w-full" disabled={form.processing}>{t.apply}<span aria-hidden="true"><ArrowIcon /></span></button>
                            <Link href="/boutique" preserveState preserveScroll className="block py-2 text-center text-xs text-stone-600 underline underline-offset-4">{t.reset}</Link>
                        </div>
                    </form>
                    {whatsapp_url && <div className="mt-5 hidden border-l-2 border-brand-gold bg-[#f1ede4] p-5 lg:block"><h2 className="font-display text-lg">{t.catalog_help}</h2><p className="mt-2 text-xs leading-6 text-stone-600">{t.catalog_help_text}</p><a href={whatsapp_url} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex min-h-11 items-center gap-2 text-xs font-medium text-brand-ink"><WhatsAppIcon className="size-5" />{t.catalog_help_link}<ArrowIcon /></a></div>}
                </aside>
                <section aria-labelledby="selection-title" aria-busy={form.processing} className="min-w-0">
                    <div className="mb-6 flex flex-wrap items-end justify-between gap-4 border-b border-black/10 pb-5"><div><h2 id="selection-title" className="font-display text-2xl">{t.catalog_selection}</h2><p className="mt-2 text-xs text-stone-500" role="status" aria-live="polite">{products.total} {t.results}</p></div><label className="flex items-center gap-3 text-xs text-stone-600">{t.sort}<select className="shop-field !w-auto !max-w-48 !border-black/15 !bg-white !py-2.5" value={form.data.sort} disabled={form.processing} onChange={(e) => { form.setData('sort', e.target.value); apply(e.target.value); }}>{['newest', 'price_asc', 'price_desc', 'name'].map((sort) => <option key={sort} value={sort}>{t[sort]}</option>)}</select></label></div>
                    {chips.length > 0 && <nav aria-label={t.active_filters} className="mb-6 flex flex-wrap items-center gap-2">{chips.map((chip) => <Link key={chip.key} href={href({ [chip.key]: chip.key === 'in_stock' ? false : '' })} preserveState preserveScroll aria-label={`${t.remove_filter} : ${chip.label}`} className="inline-flex min-h-10 max-w-full items-center gap-3 rounded-sm border border-brand-ink/20 bg-brand-gold/10 px-3 py-2 text-xs text-brand-ink"><span className="break-all">{chip.label}</span><span aria-hidden="true">×</span></Link>)}<Link href="/boutique" preserveState preserveScroll className="px-2 py-3 text-xs text-stone-600 underline underline-offset-4">{t.reset}</Link></nav>}
                    {products.items.length ? <div className="grid grid-cols-2 gap-x-3 gap-y-6 sm:gap-x-5 sm:gap-y-8 xl:grid-cols-3">{products.items.map((product, index) => <ProductCard key={product.id} product={product} index={index} />)}</div>
                        : <div className="border border-dashed border-black/20 bg-white px-6 py-14 text-center sm:px-12 sm:py-20"><span className="mx-auto mb-6 flex size-14 items-center justify-center rounded-full border border-brand-gold/40 bg-brand-cream text-brand-ink"><FilterIcon /></span><h3 className="font-display text-2xl">{t.catalog_empty_title}</h3><p className="mx-auto mt-4 max-w-sm text-sm leading-7 text-stone-600">{t.catalog_empty_text}</p><Link href="/boutique" className="primary-button mt-7">{t.catalog_all}<ArrowIcon /></Link></div>}
                    {products.last_page > 1 && <nav aria-label="Pagination" className="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-black/10 pt-6">
                        {products.previous_url ? <Link href={products.previous_url} rel="prev" className="secondary-button">{t.previous}</Link> : <span />}
                        <span className="text-xs text-stone-600">{t.page} <strong className="font-semibold text-brand-black">{products.current_page}</strong> {t.of} {products.last_page}</span>
                        {products.next_url ? <Link href={products.next_url} rel="next" className="secondary-button">{t.next}</Link> : <span />}
                    </nav>}
                </section>
            </div>
        </div>
    </ShopLayout>;
}
