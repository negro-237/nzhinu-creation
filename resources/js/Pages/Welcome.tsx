import ArrowIcon from '../Components/ArrowIcon';
import { Link, usePage } from '@inertiajs/react';
import SeoHead from '../Components/SeoHead';
import ShopLayout from '../Layouts/ShopLayout';
import ProductCard from '../Components/ProductCard';
import BoutiqueAddress from '../Components/BoutiqueAddress';
import CategoryIcon from '../Components/CategoryIcon';
import { WhatsAppIcon } from '../Components/WhatsAppContact';
import type { Market, ProductCardData, ShopProps } from '../types/shop';

type Props = {
    markets: Market[];
    featured: ProductCardData[];
    categories: { name: string; slug: string; description: string | null; icon: string }[];
    banners: { id: number; title: string; body: string; link_path: string | null; link_label: string | null }[];
    content: { title: string; title_accent: string; intro: string; status: string; eyebrow: string;
        discover: string; signature: string; logo_alt: string; markets: string; markets_intro: string;
        market_notes: Record<string, string> };
};

export default function Welcome({ markets, featured, content, banners, categories }: Props) {
    const { market, text: t, whatsapp_url } = usePage<ShopProps>().props.shop;
    const cover = featured.find((product) => product.image);
    return <ShopLayout>
        <SeoHead />
        {banners.length > 0 && <section className="shop-container grid gap-4 pt-8" aria-label={t.announcements}>{banners.map((banner) => <article data-reveal key={banner.id} className="border-l-2 border-brand-gold bg-brand-black p-5 text-white sm:p-7"><h2 className="font-display text-2xl">{banner.title}</h2><p className="mt-3 whitespace-pre-line break-words text-sm leading-7 text-stone-200">{banner.body}</p>{banner.link_path && <Link href={banner.link_path} className="mt-4 inline-block text-sm text-brand-gold underline underline-offset-4">{banner.link_label}</Link>}</article>)}</section>}
        <section className="shop-container grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-[1.05fr_1fr] lg:gap-16 lg:py-20" aria-labelledby="brand-title">
            <div data-reveal className="max-w-xl">
                <p className="mb-7 flex items-center gap-3 text-xs font-semibold tracking-[0.2em] uppercase text-brand-ink"><span className="h-px w-9 bg-brand-ink" aria-hidden="true" />{content.eyebrow}</p>
                <h1 id="brand-title" className="font-display text-5xl leading-[1.08] tracking-tight sm:text-6xl xl:text-7xl">{content.title}<br /><span className="italic text-brand-ink">{content.title_accent}</span></h1>
                <p className="mt-7 max-w-md text-base leading-8 text-stone-600 sm:text-lg">{content.intro}</p>
                <div className="mt-9 flex flex-wrap items-center gap-x-6 gap-y-4"><Link href="/boutique" className="primary-button !px-7 !py-4">{content.discover}<span aria-hidden="true"><ArrowIcon /></span></Link><Link href="/#marches" className="py-3 text-sm underline underline-offset-8">{t.visit_us}</Link></div>
                <p className="mt-7 max-w-sm border-l-2 border-brand-gold pl-4 text-xs leading-6 text-stone-600">{content.status}</p>
            </div>
            <figure data-reveal data-reveal-delay="120" className="relative mx-auto w-full max-w-lg pb-6 pl-5 sm:pl-8">
                <span className="absolute inset-y-6 left-0 right-6 border border-brand-ink/25" aria-hidden="true" />
                <div className="relative overflow-hidden bg-[#eee9de]">
                    {cover?.image ? <Link href={`/produits/${cover.slug}`} aria-label={`${t.view_product} : ${cover.name}`} className="block"><img src={cover.image.url} alt={cover.image.alt} width="640" height="800" fetchPriority="high" className="aspect-[4/5] max-h-[560px] w-full object-cover" /></Link>
                        : <img src="/images/logo-transparent.png" alt={content.logo_alt} width="1280" height="1280" fetchPriority="high" className="aspect-[4/5] max-h-[560px] w-full object-contain p-8" />}
                    <span className="absolute left-4 top-4 bg-brand-cream/95 px-4 py-2 text-[10px] font-semibold tracking-[0.18em] uppercase">{t.hero_selection}</span>
                </div>
                <figcaption className="relative -ml-5 mr-6 -mt-10 border-b-2 border-brand-gold bg-brand-black p-6 text-white sm:-ml-8 sm:p-7"><p className="text-[10px] tracking-[0.18em] uppercase text-brand-gold">{content.signature}</p><p className="mt-3 font-display text-2xl leading-tight sm:text-3xl">{t.hero_selection_intro}</p></figcaption>
            </figure>
        </section>
        <section className="border-y border-black/10 bg-white py-12 sm:py-16" aria-labelledby="featured-title">
            <div className="shop-container">
                <div data-reveal className="mb-8 flex flex-wrap items-end justify-between gap-4"><div><p className="mb-3 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink">{t.hero_selection}</p><h2 id="featured-title" className="font-display text-3xl sm:text-4xl">{t.featured}</h2><p className="mt-3 max-w-lg text-sm leading-7 text-stone-600">{t.featured_intro}</p></div><Link href="/boutique" className="py-3 text-sm underline underline-offset-8">{t.view_all} <ArrowIcon /></Link></div>
                {featured.length ? <div className="grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4 sm:gap-x-6">{featured.map((product, index) => <ProductCard key={product.id} product={product} index={index} />)}</div>
                    : <div className="border border-dashed border-black/20 p-8 text-center"><h3 className="font-display text-2xl">{t.empty_catalog}</h3><p className="mx-auto mt-3 max-w-lg text-sm leading-7 text-stone-600">{t.empty_catalog_text}</p></div>}
            </div>
        </section>
        {categories.length > 0 && <section id="categories" aria-labelledby="categories-title" className="shop-container py-12 sm:py-16">
            <div data-reveal className="mb-8 max-w-xl"><h2 id="categories-title" className="font-display text-3xl sm:text-4xl">{t.our_categories}</h2><p className="mt-4 text-sm leading-7 text-stone-600">{t.categories_intro}</p></div>
            <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{categories.map((category, index) => <li key={category.slug} data-reveal data-reveal-delay={Math.min(index % 3, 2) * 60}>
                <Link href={`/boutique?category=${encodeURIComponent(category.slug)}`} aria-label={`${t.browse_category} : ${category.name}`} className="shop-product-link group flex h-full min-w-0 flex-col items-start border border-black/10 bg-white p-6 transition-colors hover:border-brand-gold sm:p-8">
                    <span className="mb-6 flex size-20 items-center justify-center rounded-full border border-brand-gold/30 bg-brand-cream text-brand-ink"><CategoryIcon icon={category.icon} /></span>
                    <h3 className="break-words font-display text-2xl group-hover:text-brand-ink">{category.name}</h3>
                    {category.description && <p className="mt-3 whitespace-pre-line break-words text-sm leading-7 text-stone-600">{category.description}</p>}
                    <span className="mt-auto flex items-center gap-3 pt-6 text-xs font-semibold text-brand-ink">{t.explore_category}<span aria-hidden="true"><ArrowIcon /></span></span>
                </Link>
            </li>)}</ul>
        </section>}
        <section aria-labelledby="journey-title" className="border-y border-black/10 bg-[#f1ede4] py-12 sm:py-16"><div className="shop-container"><div data-reveal className="max-w-xl"><p className="mb-4 text-xs font-semibold tracking-[0.18em] uppercase text-brand-ink">{t.journey_eyebrow}</p><h2 id="journey-title" className="font-display text-3xl sm:text-4xl">{t.journey_title}</h2></div><ol className="mt-10 grid gap-8 lg:grid-cols-3">{[1, 2, 3].map((step) => <li key={step} data-reveal data-reveal-delay={(step - 1) * 60} className="border-t border-black/15 pt-6"><span className="font-display text-3xl italic text-brand-ink">0{step}</span><h3 className="mt-5 font-display text-xl">{t[`journey_${step}_title`]}</h3><p className="mt-3 max-w-sm text-sm leading-7 text-stone-600">{t[`journey_${step}_text`]}</p></li>)}</ol></div></section>
        <section id="marches" aria-labelledby="markets-title" className="shop-container scroll-mt-28 py-12 sm:py-20">
            <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><h2 id="markets-title" className="font-display text-3xl sm:text-4xl">{content.markets}</h2><p className="max-w-sm text-sm leading-6 text-stone-600">{content.markets_intro}</p></div>
            <div data-reveal className="mb-6 grid overflow-hidden border border-black/10 bg-white lg:grid-cols-2"><div className="bg-brand-black p-7 text-white sm:p-10"><p className="text-[10px] tracking-[0.18em] uppercase text-brand-gold">{t.visit_eyebrow}</p><p className="mt-5 font-display text-3xl leading-tight">Nzhinu Création</p><p className="mt-5 max-w-md text-sm leading-7 text-stone-300">{t.visit_intro}</p>{whatsapp_url && <a href={whatsapp_url} target="_blank" rel="noopener noreferrer" className="primary-button mt-7"><WhatsAppIcon />{t.contact_team}</a>}</div><div className="flex flex-col justify-center p-7 sm:p-10"><BoutiqueAddress /></div></div>
            {markets.length > 1 && <ul className="grid gap-5 sm:grid-cols-2">{markets.map((item) => <li data-reveal key={item.code} className={`flex items-start gap-5 border bg-white p-6 sm:p-8 ${item.code === market.code ? 'border-brand-ink' : 'border-black/10'}`}><span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-cream text-xs font-semibold tracking-widest">{item.code}</span><div><h3 className="font-display text-2xl">{item.name}</h3><p className="mt-2 text-sm leading-6 text-stone-600">{content.market_notes[item.code]}</p><p className="mt-4 text-xs font-semibold tracking-widest text-brand-ink">{item.currency}</p></div></li>)}</ul>}
        </section>
    </ShopLayout>;
}
