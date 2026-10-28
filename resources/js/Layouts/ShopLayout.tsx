import ArrowIcon from '../Components/ArrowIcon';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useStorefrontMotion } from '../lib/useStorefrontMotion';
import ShopFooter from '../Components/ShopFooter';
import WhatsAppContact from '../Components/WhatsAppContact';
import CategoryIcon from '../Components/CategoryIcon';
import type { ShopProps } from '../types/shop';

export default function ShopLayout({ children, privatePage = false }: { children: ReactNode; privatePage?: boolean }) {
    const { shop, flash, canonical, seo } = usePage<ShopProps>().props;
    const { url } = usePage<ShopProps>();
    const root = useRef<HTMLDivElement>(null);
    useStorefrontMotion(root, url);
    const { market, markets, text: t } = shop;
    const [pending, setPending] = useState<string | null>(null);
    const [menuOpen, setMenuOpen] = useState(false);
    const menuButton = useRef<HTMLButtonElement>(null);
    const header = useRef<HTMLElement>(null);
    useEffect(() => setMenuOpen(false), [url]);
    useEffect(() => {
        if (!menuOpen) return;
        function close(event: KeyboardEvent) {
            if (event.key === 'Escape') { setMenuOpen(false); menuButton.current?.focus(); }
        }
        function outside(event: PointerEvent) {
            if (event.target instanceof Node && !header.current?.contains(event.target)) setMenuOpen(false);
        }
        document.addEventListener('keydown', close);
        document.addEventListener('pointerdown', outside);
        return () => { document.removeEventListener('keydown', close); document.removeEventListener('pointerdown', outside); };
    }, [menuOpen]);
    const form = useForm({ code: market.code as string, clear_cart: false });
    function changeMarket(code: string, clear = false) {
        form.transform(() => ({ code, clear_cart: clear }));
        form.post('/marche', {
            preserveScroll: false, preserveState: false, onSuccess: () => setPending(null),
        });
    }
    return (
        <div ref={root} className="storefront min-h-screen bg-brand-cream text-brand-black">
            <Head><link head-key="canonical" rel="canonical" href={seo?.canonical ?? canonical as string} /></Head>
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:p-4">{t.skip}</a>
            <div className="bg-brand-black px-5 py-2.5 text-center text-xs tracking-wide text-white"><span aria-hidden="true" className="mr-2 inline-block size-1.5 rounded-full bg-brand-gold" />{t.notice}</div>
            <header ref={header} className="sticky top-0 z-40 border-b border-black/10 bg-brand-cream/95 backdrop-blur-md" onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setMenuOpen(false); }}>
              <div className="shop-container py-3 sm:py-4">
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-4 sm:gap-x-6">
                    <Link href="/" className="flex min-h-11 items-center gap-2 sm:gap-3" aria-label={t.brand}>
                        <img src="/images/logo-transparent.png" alt="" width="64" height="64" className="size-10 object-contain min-[360px]:size-12 sm:size-16" />
                        <span className="hidden max-w-16 text-[10px] font-semibold tracking-[0.08em] uppercase min-[360px]:block min-[390px]:max-w-20 min-[390px]:text-[11px] sm:max-w-24 sm:text-xs xl:max-w-none xl:text-sm">{t.brand}</span>
                    </Link>
                    <nav aria-label={t.nav} className="ml-auto flex items-center gap-1 text-sm lg:gap-6">
                        <Link href="/" aria-current={url === '/' ? 'page' : undefined} className="shop-nav-link hidden py-3 hover:text-brand-ink lg:block">{t.home}</Link>
                        <Link href="/boutique" aria-label={t.catalog} aria-current={url.startsWith('/boutique') ? 'page' : undefined} className="shop-nav-link flex size-11 items-center justify-center hover:text-brand-ink lg:w-auto">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-5 lg:hidden" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg><span className="hidden lg:inline">{t.catalog}</span>
                        </Link>
                        <Link href="/#marches" className="shop-nav-link hidden py-3 hover:text-brand-ink lg:block">{t.visit_us}</Link>
                        <Link href="/panier" aria-label={`${t.cart} (${shop.cart_count})`} aria-current={url === '/panier' ? 'page' : undefined} className="relative flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-full hover:bg-black/5 lg:border lg:border-black/15 lg:px-4">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-5" aria-hidden="true"><path d="M5 7h14l1 14H4L5 7Z" /><path d="M8 9V6a4 4 0 0 1 8 0v3" /></svg><span className="hidden xl:inline">{t.cart}</span><span key={shop.cart_count} data-cart-count className="shop-count absolute right-0 top-0 flex min-w-4 items-center justify-center rounded-full bg-brand-black px-1 text-[10px] text-white lg:static lg:min-w-5 lg:py-0.5">{shop.cart_count}</span>
                        </Link>
                        <WhatsAppContact floatingContainer={root} />
                        <button ref={menuButton} type="button" aria-label={menuOpen ? t.close_menu : t.open_menu} aria-expanded={menuOpen} aria-controls="shop-menu" onClick={() => setMenuOpen(!menuOpen)} className="flex min-h-11 cursor-pointer items-center gap-2 rounded-full px-3 hover:bg-black/5">
                            <span className="hidden text-xs lg:inline">{t.menu_categories}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-5" aria-hidden="true">{menuOpen ? <path d="m6 6 12 12M6 18 18 6" /> : <path d="M3 6h18M3 12h18M3 18h18" />}</svg>
                        </button>
                    </nav>
                    {!privatePage && markets.length > 1 && <div className="flex items-center gap-3 text-xs">
                        <label htmlFor="shop-market" className="text-stone-600">{t.market}</label>
                        <select id="shop-market" className="shop-field !w-auto !py-2" value={pending ?? market.code} disabled={form.processing} onChange={(event) => {
                            const code = event.target.value;
                            if (code === market.code) { setPending(null); return; }
                            if (shop.cart_count > 0) setPending(code); else changeMarket(code);
                        }}>
                            {markets.map((item) => <option key={item.code} value={item.code}>{item.name} · {item.currency}</option>)}
                        </select>
                    </div>}
                </div>
                {menuOpen && <div id="shop-menu" className="shop-feedback mt-4 max-h-[65dvh] overflow-y-auto overscroll-contain border-t border-black/10 py-6" onClick={(event) => { if ((event.target as HTMLElement).closest('a')) setMenuOpen(false); }}>
                    <div className="grid gap-8 lg:grid-cols-[2fr_1fr]">
                        <nav aria-label={t.menu_categories}><p className="mb-4 text-xs font-semibold tracking-widest text-brand-ink uppercase">{t.menu_categories}</p><ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">{shop.categories.map((category) => <li key={category.slug}><Link href={`/boutique?category=${encodeURIComponent(category.slug)}`} className="flex items-center gap-3 rounded-sm border border-transparent p-3 hover:border-brand-gold/40 hover:bg-white"><span className="flex size-10 shrink-0 items-center justify-center text-brand-ink"><CategoryIcon icon={category.icon} /></span><span className="text-sm">{category.name}</span></Link></li>)}</ul><Link href="/boutique" className="mt-4 inline-block py-2 text-sm underline underline-offset-4">{t.footer_catalog} <ArrowIcon /></Link></nav>
                        <nav aria-label={t.information} className="border-t border-black/10 pt-5 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0"><p className="mb-3 text-xs font-semibold tracking-widest text-brand-ink uppercase">{t.information}</p><Link href="/" className="block py-3 text-sm hover:text-brand-ink">{t.home}</Link><Link href="/#marches" className="block py-3 text-sm hover:text-brand-ink">{t.visit_us}</Link>{shop.pages.map((page) => <Link key={page.slug} href={`/pages/${page.slug}`} className="block py-3 text-sm hover:text-brand-ink">{page.title}</Link>)}{shop.contact_email && <a href={`mailto:${shop.contact_email}`} className="block break-all py-3 text-sm text-brand-ink">{shop.contact_email}</a>}</nav>
                    </div>
                </div>}
                {pending && <div className="shop-feedback mt-5 border-l-2 border-brand-gold bg-white p-4" role="alert">
                    <p className="text-sm leading-6">{t.switch_warning}</p>
                    <div className="mt-3 flex flex-wrap gap-3">
                        <button type="button" disabled={form.processing} className="primary-button" onClick={() => changeMarket(pending, true)}>{t.switch_confirm}</button>
                        <button type="button" className="secondary-button" disabled={form.processing} onClick={() => setPending(null)}>{t.cancel}</button>
                    </div>
                </div>}
                {form.errors.code && <p className="shop-error mt-3" role="alert">{form.errors.code}</p>}
              </div>
            </header>
            {flash.success && <div className="shop-feedback shop-container py-4" role="status" aria-live="polite"><p className="border-l-2 border-brand-gold bg-white p-4 text-sm">{flash.success}</p></div>}
            <main id="main" tabIndex={-1} className="shop-page-enter">{children}</main>
            <ShopFooter />
        </div>
    );
}
