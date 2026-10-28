import { Link, usePage } from '@inertiajs/react';
import type { ShopProps } from '../types/shop';
import BoutiqueAddress from './BoutiqueAddress';

function SocialIcon({ network }: { network: string }) {
    return <svg viewBox="0 0 24 24" className="size-4" fill="currentColor" aria-hidden="true" focusable="false">
        {network === 'facebook' ? <path d="M14 22v-9h3l.5-4H14V6.5c0-1.1.3-1.5 1.7-1.5H18V1.4A27 27 0 0 0 14.8 1C11.5 1 10 3 10 6v3H7v4h3v9h4Z" />
            : network === 'youtube' ? <><rect x="2" y="5" width="20" height="14" rx="4" /><path d="m10 9 6 3-6 3V9Z" fill="var(--color-brand-cream)" /></>
                : <path d="M15 2h-4v13a2.5 2.5 0 1 1-2-2.4V8.5a6.5 6.5 0 1 0 6 6.5V8a9 9 0 0 0 6 2V6a6 6 0 0 1-6-4Z" />}
    </svg>;
}

export default function ShopFooter() {
    const { shop } = usePage<ShopProps>().props;
    const { text: t } = shop;
    return <footer className="mt-16 border-t border-black/10 bg-[#f1ede4] text-brand-black">
        <div className="shop-container py-12 sm:py-16">
            <div className="mb-10 h-px w-16 bg-brand-gold" aria-hidden="true" />
            <div className="grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-[1.25fr_0.75fr_1fr_1.2fr] lg:gap-x-10">
                <div className="min-w-0 sm:col-span-2 lg:col-span-1">
                    <Link href="/" aria-label={t.footer_brand_link} className="inline-flex items-center gap-3">
                        <img src="/images/logo-transparent.png" alt="" width="96" height="112" className="h-28 w-20 object-contain" loading="lazy" />
                        <span className="font-display text-2xl leading-tight">Nzhinu<br /><span className="text-brand-ink italic">Création</span></span>
                    </Link>
                    <p className="mt-5 max-w-xs text-sm leading-7 text-stone-600">{t.footer_intro}</p>
                    {shop.social_links.length > 0 && <nav aria-label={t.social_networks} className="mt-6 flex flex-wrap gap-3">
                        {shop.social_links.map((social) => <a key={social.network} href={social.url} target="_blank" rel="noopener noreferrer" aria-label={`${social.label} — ${t.new_tab}`} className="inline-flex min-h-11 items-center gap-2 rounded-full border border-black/15 px-4 py-2 text-xs font-medium transition-colors hover:border-brand-ink hover:bg-brand-ink hover:text-white"><SocialIcon network={social.network} />{social.label}</a>)}
                    </nav>}
                </div>
                <nav aria-label={t.footer_navigation} className="min-w-0">
                    <h2 className="text-xs font-semibold tracking-[0.16em] uppercase">{t.footer_explore}</h2>
                    <ul className="mt-5 space-y-3 text-sm text-stone-600">
                        <li><Link href="/" className="inline-block py-1 hover:text-brand-ink">{t.home}</Link></li>
                        <li><Link href="/boutique" className="inline-block py-1 hover:text-brand-ink">{t.footer_catalog}</Link></li>
                        <li><Link href="/panier" className="inline-block py-1 hover:text-brand-ink">{t.footer_cart}</Link></li>
                    </ul>
                </nav>
                <div className="min-w-0">
                    <h2 className="text-xs font-semibold tracking-[0.16em] uppercase">{t.information}</h2>
                    {shop.pages.length > 0 && <nav aria-label={t.information}><ul className="mt-5 space-y-3 text-sm text-stone-600">{shop.pages.map((page) => <li key={page.slug}><Link href={`/pages/${page.slug}`} className="inline-block break-words py-1 hover:text-brand-ink">{page.title}</Link></li>)}</ul></nav>}
                    <p className="mt-5 text-sm leading-7 text-stone-600">{t.footer_order_info}</p>
                    <p className="mt-3 text-sm leading-7 text-stone-600">{t.footer_payment_info}</p>
                </div>
                <div className="min-w-0 border-t border-black/10 pt-8 sm:border-t-0 sm:pt-0">
                    <h2 className="mb-5 text-xs font-semibold tracking-[0.16em] uppercase">{t.footer_visit}</h2>
                    <div className="text-stone-600"><BoutiqueAddress showTitle={false} /></div>
                    {shop.whatsapp_url && <a href={shop.whatsapp_url} target="_blank" rel="noopener noreferrer" className="mt-6 inline-flex min-h-11 items-center gap-3 border-b border-brand-ink py-2 text-sm font-medium text-brand-ink">{t.whatsapp}<span aria-hidden="true">↗</span></a>}
                </div>
            </div>
        </div>
        <div className="bg-brand-black py-5 text-stone-300">
            <div className="shop-container flex flex-col justify-between gap-3 text-xs leading-6 sm:flex-row sm:items-center">
                <p>© {new Date().getFullYear()} {t.brand}</p>
                <p className="tracking-wide">{t.footer_signature}</p>
            </div>
        </div>
    </footer>;
}
