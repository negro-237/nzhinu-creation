import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { useStorefrontMotion } from '../lib/useStorefrontMotion';
import ShopFooter from '../Components/ShopFooter';
import type { ShopProps } from '../types/shop';

export default function ShopLayout({ children, privatePage = false }: { children: ReactNode; privatePage?: boolean }) {
    const { shop, flash, canonical } = usePage<ShopProps>().props;
    const { url } = usePage<ShopProps>();
    const root = useRef<HTMLDivElement>(null);
    useStorefrontMotion(root, url);
    const { market, markets, text: t } = shop;
    const [pending, setPending] = useState<string | null>(null);
    const form = useForm({ code: market.code as string, clear_cart: false });
    function changeMarket(code: string, clear = false) {
        form.transform(() => ({ code, clear_cart: clear }));
        form.post('/marche', {
            preserveScroll: false, preserveState: false, onSuccess: () => setPending(null),
        });
    }
    return (
        <div ref={root} className="storefront min-h-screen bg-brand-cream text-brand-black">
            <Head><link head-key="canonical" rel="canonical" href={canonical as string} /></Head>
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-white focus:p-4">{t.skip}</a>
            <div className="bg-brand-black px-5 py-2.5 text-center text-xs tracking-wide text-white"><span aria-hidden="true" className="mr-2 inline-block size-1.5 rounded-full bg-brand-gold" />{t.notice}</div>
            <header className="shop-container border-b border-black/10 py-5">
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-5 sm:gap-x-8">
                    <Link href="/" className="flex items-center gap-3" aria-label={t.brand}>
                        <img src="/images/logo-transparent.png" alt="" width="64" height="64" className="size-12 object-contain sm:size-16" />
                        <span className="max-w-24 text-xs font-semibold tracking-[0.1em] uppercase sm:max-w-none sm:text-base">{t.brand}</span>
                    </Link>
                    <nav aria-label={t.nav} className="flex items-center gap-4 text-sm sm:gap-6">
                        <Link href="/boutique" className="shop-nav-link hover:text-brand-ink">{t.catalog}</Link>
                        <Link href="/panier" className="shop-nav-link flex items-center gap-2 hover:text-brand-ink">{t.cart}<span key={shop.cart_count} data-cart-count className="shop-count rounded-full bg-brand-black px-2.5 py-1 text-xs text-white">{shop.cart_count}</span></Link>
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
                {pending && <div className="shop-feedback mt-5 border-l-2 border-brand-gold bg-white p-4" role="alert">
                    <p className="text-sm leading-6">{t.switch_warning}</p>
                    <div className="mt-3 flex flex-wrap gap-3">
                        <button type="button" disabled={form.processing} className="primary-button" onClick={() => changeMarket(pending, true)}>{t.switch_confirm}</button>
                        <button type="button" className="secondary-button" disabled={form.processing} onClick={() => setPending(null)}>{t.cancel}</button>
                    </div>
                </div>}
                {form.errors.code && <p className="shop-error mt-3" role="alert">{form.errors.code}</p>}
            </header>
            {flash.success && <div className="shop-feedback shop-container py-4" role="status" aria-live="polite"><p className="border-l-2 border-brand-gold bg-white p-4 text-sm">{flash.success}</p></div>}
            <main id="main" tabIndex={-1} className="shop-page-enter">{children}</main>
            <ShopFooter />
        </div>
    );
}
