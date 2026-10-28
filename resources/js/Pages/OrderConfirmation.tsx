import { Head, Link } from '@inertiajs/react';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/money';
import type { Market } from '../types/shop';

type Props = { content: Record<string, string>; order: { number: string; market: Market; status: string; status_code: string; payment_status: string; fulfillment_status: string;
    method: string; phone_hint: string; fulfillment: { name: string | null; address?: string; country: string }; expires_at: string;
    subtotal_minor: number; total_minor: number | null; shipping_fee_minor: number | null; tax_minor: number | null;
    verified_paid_minor: number; verified_refunded_minor: number; verified_net_minor: number; outstanding_minor: number | null;
    handover: { at: string; carrier: string | null; reference: string } | null;
    whatsapp_url: string | null;
    items: { product_name: string; variant_label: string; sku: string; quantity: number; unit_price_minor: number; line_total_minor: number }[] } };

export default function OrderConfirmation({ order, content: t }: Props) {
    const pending = order.status_code === 'pending';
    const title = pending ? t.confirmation_title : t.tracking_title;
    return <ShopLayout privatePage><Head title={title}><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <div className="shop-container max-w-4xl py-12 sm:py-16">

            <h1 className="font-display text-4xl sm:text-5xl">{title}</h1><p className="mt-5 text-sm leading-7 text-stone-600">{pending ? t.received : t.tracking_intro}</p>
            <div className="mt-8 border border-black/10 bg-white p-6 sm:p-8">
                <p className="text-xs text-stone-600">{t.number}</p><p className="mt-2 break-words text-xl font-semibold">{order.number}</p>
                <dl className="mt-6 grid gap-5 text-sm sm:grid-cols-2">{[[t.status, order.status], [t.payment, order.payment_status], [t.fulfillment, order.fulfillment_status], [t.phone_hint, order.phone_hint]].map(([label, value]) => <div key={label}><dt className="text-stone-600">{label}</dt><dd className="mt-1 font-medium">{value}</dd></div>)}</dl>
                <p className="mt-6 text-sm">{t[order.method]} · {order.fulfillment.name ?? t.zone_unspecified}</p>{order.fulfillment.address && <p className="mt-2 text-sm text-stone-600">{order.fulfillment.address}</p>}
                <ul className="mt-6 divide-y divide-black/10 border-y border-black/10">{order.items.map((item, index) => <li key={index} className="py-4 text-sm"><p className="font-medium">{item.product_name} · {item.variant_label}</p><p className="mt-2 text-stone-600">{item.sku} · {item.quantity} × {money(item.unit_price_minor, order.market)}</p><p className="mt-2 font-medium">{money(item.line_total_minor, order.market)}</p></li>)}</ul>
                <p className="mt-6 text-sm">{t.subtotal} : <strong>{money(order.subtotal_minor, order.market)}</strong></p>
                <p className="mt-4 text-lg">{t.total} : {order.total_minor === null ? t.awaiting_total : money(order.total_minor, order.market)}</p>
                <dl className="mt-6 grid gap-4 border-t border-black/10 pt-5 text-sm sm:grid-cols-2">{[[t.verified_paid, money(order.verified_paid_minor, order.market)], [t.verified_refunded, money(order.verified_refunded_minor, order.market)], [t.verified_net, money(order.verified_net_minor, order.market)], [t.outstanding, order.outstanding_minor === null ? t.awaiting_total : money(order.outstanding_minor, order.market)]].map(([label, value]) => <div key={label}><dt className="text-stone-600">{label}</dt><dd className="mt-1 font-medium">{value}</dd></div>)}</dl>
                {order.handover ? <p className="mt-5 border-l-2 border-brand-gold p-3 text-sm">{t.handed_over} : {new Date(order.handover.at).toLocaleString('fr-FR')} · {order.handover.carrier} · {order.handover.reference}</p> : <p className="mt-5 text-xs text-stone-600">{t.deadline} : {new Date(order.expires_at).toLocaleString('fr-FR')}</p>}
            </div>
            <p className="mt-6 border-l-2 border-brand-gold bg-white p-4 text-sm leading-7">{t.not_payment}</p><p className="mt-4 text-xs leading-6 text-stone-600">{t.keep_link}</p>
            <Link href="/boutique" className="primary-button mt-7">{t.continue}</Link>
            {order.whatsapp_url && <a href={order.whatsapp_url} target="_blank" rel="noopener noreferrer" className="secondary-button ml-3 mt-7">{t.whatsapp}</a>}
        </div>
    </ShopLayout>;
}
