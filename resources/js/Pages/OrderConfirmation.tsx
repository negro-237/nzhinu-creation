import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import ArrowIcon from '../Components/ArrowIcon';
import { WhatsAppIcon } from '../Components/WhatsAppContact';
import { money } from '../lib/money';
import type { Market } from '../types/shop';

type OrderSummary = {
    number: string; market: Market; status: string; status_code: string; payment_status: string; payment_status_code: string;
    fulfillment_status: string; fulfillment_status_code: string; method: string; phone_hint: string;
    fulfillment: { name: string | null; address?: string; country: string }; expires_at: string;
    subtotal_minor: number; total_minor: number | null; shipping_fee_minor: number | null; tax_minor: number | null;
    verified_paid_minor: number; verified_refunded_minor: number; verified_net_minor: number; outstanding_minor: number | null;
    handover: { at: string; carrier: string | null; reference: string } | null;
    whatsapp_url: string | null;
    items: { product_name: string; variant_label: string; sku: string; quantity: number; unit_price_minor: number; line_total_minor: number }[];
};
type Props = { content: Record<string, string>; order: OrderSummary };

function CheckIcon() {
    return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="size-5" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>;
}
function date(value: string) {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? '—' : new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(parsed);
}

export default function OrderConfirmation({ order, content: t }: Props) {
    const [refreshing, setRefreshing] = useState(false);
    const pending = order.status_code === 'pending';
    const stopped = ['cancelled', 'expired'].includes(order.status_code);
    const refunded = ['refunded', 'partially_refunded'].includes(order.payment_status_code);
    const title = pending ? t.confirmation_title : t.tracking_title;
    const steps = [
        { label: t.tracking_registered, done: true },
        { label: t.tracking_confirmation, done: ['confirmed', 'completed'].includes(order.status_code) },
        { label: t.tracking_payment, done: order.payment_status_code === 'paid' },
        { label: t.tracking_preparation, done: ['packed', 'shipped', 'collected'].includes(order.fulfillment_status_code) },
        { label: order.method === 'pickup' ? t.tracking_collected : t.tracking_shipped, done: !!order.handover },
    ];
    const currentStep = steps.findIndex((step) => !step.done);
    const amount = (value: number | null) => value === null ? t.confirm_later : money(value, order.market);
    function refresh() {
        router.reload({ only: ['order'], onStart: () => setRefreshing(true), onFinish: () => setRefreshing(false) });
    }
    return <ShopLayout privatePage>
        <Head title={title}><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section aria-labelledby="tracking-title" className="border-b border-black/10"><div className="shop-container pb-8 pt-6 sm:pb-10 sm:pt-8">
            <Link href="/boutique" className="mb-6 inline-flex min-h-10 items-center gap-2 text-xs text-brand-ink"><span aria-hidden="true" className="rotate-180"><ArrowIcon /></span>{t.continue}</Link>
            <div className="grid items-end gap-7 lg:grid-cols-[1.5fr_1fr] lg:gap-12"><div><p className="mb-4 flex items-center gap-3 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink"><span className="h-px w-8 bg-brand-ink" aria-hidden="true" />{t.tracking_eyebrow}</p><h1 id="tracking-title" className="font-display text-4xl tracking-tight sm:text-5xl">{title}</h1><p className="mt-4 max-w-xl text-sm leading-7 text-stone-600">{pending ? t.received : t.tracking_intro}</p></div>
                <div className="border-l-2 border-brand-gold bg-brand-black p-5 text-white sm:p-7"><p className="text-[10px] tracking-widest uppercase text-brand-gold">{t.number}</p><p className="mt-3 break-all text-lg font-semibold tracking-wide">{order.number}</p><p className="mt-3 text-xs leading-6 text-stone-300">{t.tracking_private}</p></div>
            </div>
        </div></section>
        <div className="shop-container py-8 sm:py-12">
            <div className="grid gap-3 sm:grid-cols-3">{[[t.status, order.status], [t.payment, order.payment_status], [t.fulfillment, order.fulfillment_status]].map(([label, value], index) => <div key={label} className={`border bg-white p-5 ${index === 0 && stopped ? 'border-red-700/25' : 'border-black/10'}`}><p className="text-[10px] tracking-widest uppercase text-stone-500">{label}</p><p className={`mt-3 font-display text-xl ${index === 0 && stopped ? 'text-red-800' : 'text-brand-black'}`}>{value}</p></div>)}</div>
            <div className="mt-5 flex flex-wrap items-center justify-between gap-4 border-b border-black/10 pb-6"><p className="text-xs leading-6 text-stone-500">{t.tracking_phone} · {order.phone_hint}</p><button type="button" disabled={refreshing} onClick={refresh} className="secondary-button min-h-11 gap-2 !text-xs"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className={`size-4 ${refreshing ? 'animate-spin motion-reduce:animate-none' : ''}`} aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5" /><path d="M6 6a8 8 0 0 1 13 2l1 4M4 12l1 4a8 8 0 0 0 13 2" /></svg>{refreshing ? t.tracking_refreshing : t.tracking_refresh}</button></div>
            {stopped ? <p role="status" className="mt-6 border-l-2 border-brand-ink bg-white p-5 text-sm leading-7">{order.status_code === 'expired' ? t.tracking_expired : t.tracking_cancelled}</p>
                : refunded ? <p role="status" className="mt-6 border-l-2 border-brand-gold bg-white p-5 text-sm leading-7">{t.tracking_refunds}</p>
                    : <section aria-labelledby="tracking-progress-title" className="mt-7 border border-black/10 bg-white p-5 sm:p-7"><h2 id="tracking-progress-title" className="font-display text-2xl">{t.tracking_progress}</h2><ol className="mt-6 grid gap-5 sm:grid-cols-5">{steps.map((step, index) => <li key={step.label} aria-current={index === currentStep ? 'step' : undefined} className="flex items-center gap-3 sm:flex-col sm:items-start"><span aria-hidden="true" className={`flex size-9 shrink-0 items-center justify-center rounded-full border text-xs ${step.done ? 'border-brand-black bg-brand-black text-brand-gold' : index === currentStep ? 'border-brand-ink bg-brand-gold/10 text-brand-ink' : 'border-black/15 text-stone-400'}`}>{step.done ? <CheckIcon /> : `0${index + 1}`}</span><span className={`text-xs leading-6 ${step.done || index === currentStep ? 'text-brand-black' : 'text-stone-500'}`}>{step.label}<span className="sr-only"> — {step.done ? t.tracking_done : index === currentStep ? t.tracking_current : t.tracking_next}</span></span></li>)}</ol></section>}
            <div className="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-10">
                <div className="min-w-0 space-y-6">
                    <section aria-labelledby="tracking-items-title" className="border border-black/10 bg-white"><div className="border-b border-black/10 p-5 sm:p-7"><p className="mb-3 text-[10px] tracking-widest uppercase text-brand-ink">{t.tracking_registered}</p><h2 id="tracking-items-title" className="font-display text-2xl">{t.summary}</h2></div><ul className="divide-y divide-black/10 px-5 sm:px-7">{order.items.map((item, index) => <li key={`${item.sku}-${index}`} className="py-5"><h3 className="break-words font-display text-xl">{item.product_name}</h3><p className="mt-2 break-words text-xs leading-6 text-stone-500">{item.variant_label}</p><p className="mt-1 break-all text-[11px] leading-6 text-stone-500">{item.sku}</p><div className="mt-4 flex flex-wrap items-end justify-between gap-3 text-sm"><p className="text-stone-600">{item.quantity} × {money(item.unit_price_minor, order.market)}</p><p className="font-semibold">{money(item.line_total_minor, order.market)}</p></div></li>)}</ul></section>
                    <section aria-labelledby="tracking-reception-title" className="border border-black/10 bg-white p-5 sm:p-7"><p className="mb-3 text-[10px] tracking-widest uppercase text-brand-ink">{t[order.method]}</p><h2 id="tracking-reception-title" className="font-display text-2xl">{t.tracking_reception}</h2><p className="mt-5 text-sm font-medium">{order.fulfillment.name ?? t.zone_unspecified}</p>{order.fulfillment.address && <address className="mt-3 break-words text-sm not-italic leading-7 text-stone-600">{order.fulfillment.address}</address>}
                        {order.handover ? <div className="mt-5 border-l-2 border-brand-gold bg-brand-cream p-4"><h3 className="text-sm font-medium">{order.method === 'pickup' ? t.tracking_collected : t.tracking_shipped}</h3><dl className="mt-3 space-y-3 text-xs leading-6">{[[t.tracking_date, date(order.handover.at)], [t.tracking_carrier, order.handover.carrier], [t.tracking_reference, order.handover.reference]].filter(([, value]) => value).map(([label, value]) => <div key={label}><dt className="text-stone-500">{label}</dt><dd className="break-words font-medium">{value}</dd></div>)}</dl></div> : <div className="mt-5 border-t border-black/10 pt-4"><p className="text-xs text-stone-500">{stopped || refunded ? t.tracking_reservation_date : t.deadline}</p><p className="mt-2 text-sm font-medium">{date(order.expires_at)}</p></div>}
                    </section>
                    <section aria-labelledby="tracking-ledger-title" className="border border-black/10 bg-white p-5 sm:p-7"><h2 id="tracking-ledger-title" className="font-display text-2xl">{t.tracking_ledger}</h2><p className="mt-3 text-xs leading-7 text-stone-500">{t.tracking_ledger_note}</p><dl className="mt-5 grid gap-5 border-t border-black/10 pt-5 sm:grid-cols-2">{[[t.verified_paid, money(order.verified_paid_minor, order.market)], [t.verified_refunded, money(order.verified_refunded_minor, order.market)], [t.verified_net, money(order.verified_net_minor, order.market)], [stopped || refunded ? t.tracking_difference : t.outstanding, order.outstanding_minor === null ? t.awaiting_total : money(order.outstanding_minor, order.market)]].map(([label, value]) => <div key={label}><dt className="text-xs text-stone-500">{label}</dt><dd className="mt-2 break-words text-lg font-medium">{value}</dd></div>)}</dl></section>
                </div>
                <aside aria-labelledby="tracking-finances-title" className="min-w-0 lg:sticky lg:top-28"><div className="border-t-2 border-brand-gold bg-brand-black p-6 text-white sm:p-7"><p className="text-[10px] tracking-widest uppercase text-brand-gold">{t.tracking_finances}</p><h2 id="tracking-finances-title" className="mt-4 font-display text-2xl">{t.total}</h2><p className={`mt-4 break-words font-display ${order.total_minor === null ? 'text-2xl' : 'text-3xl'}`}>{order.total_minor === null ? t.awaiting_total : money(order.total_minor, order.market)}</p><dl className="mt-6 space-y-4 border-t border-white/15 pt-5">{[[t.subtotal, amount(order.subtotal_minor)], [t.tracking_fees, amount(order.shipping_fee_minor)], [t.tracking_taxes, amount(order.tax_minor)]].map(([label, value]) => <div key={label} className="flex flex-wrap justify-between gap-x-3 gap-y-1 text-xs leading-6"><dt className="text-stone-300">{label}</dt><dd className="font-medium">{value}</dd></div>)}</dl>{pending && <p className="mt-5 text-xs leading-7 text-stone-300">{t.intro}</p>}</div>
                    <div className="border border-t-0 border-black/10 bg-white p-5 sm:p-7"><h3 className="font-display text-xl">{t.tracking_private}</h3><p className="mt-3 text-xs leading-7 text-stone-600">{t.keep_link}</p>{order.whatsapp_url && <a href={order.whatsapp_url} target="_blank" rel="noopener noreferrer" className="mt-5 flex min-h-12 items-center justify-center gap-2 border border-brand-ink/20 px-3 py-3 text-xs text-brand-ink hover:border-brand-ink"><WhatsAppIcon className="size-5 shrink-0" />{t.whatsapp}<ArrowIcon /></a>}<Link href="/boutique" className="primary-button mt-3 min-h-12 w-full">{t.continue}<ArrowIcon /></Link></div>
                </aside>
            </div>
        </div>
    </ShopLayout>;
}
