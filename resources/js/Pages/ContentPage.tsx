import SeoHead from '../Components/SeoHead';
import ShopLayout from '../Layouts/ShopLayout';
import { Link, usePage } from '@inertiajs/react';
import ArrowIcon from '../Components/ArrowIcon';
import type { ShopProps } from '../types/shop';

export default function ContentPage({ page }: { page: { title: string; body: string; meta_description: string } }) {
    const { text: t } = usePage<ShopProps>().props.shop;
    return <ShopLayout><SeoHead />
        <section className="border-b border-black/10"><div className="shop-container py-8 sm:py-10"><Link href="/" className="mb-6 inline-flex min-h-10 items-center gap-2 text-xs text-brand-ink"><span aria-hidden="true" className="rotate-180"><ArrowIcon /></span>{t.home}</Link><p className="mb-4 flex items-center gap-3 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink"><span aria-hidden="true" className="h-px w-8 bg-brand-ink" />{t.information}</p><h1 className="max-w-3xl font-display text-4xl tracking-tight sm:text-5xl">{page.title}</h1></div></section>
        <div className="shop-container py-8 sm:py-12"><article className="max-w-3xl border border-black/10 bg-white p-5 sm:p-10"><div className="whitespace-pre-line break-words text-base leading-8 text-stone-700">{page.body}</div></article></div>
    </ShopLayout>;
}
