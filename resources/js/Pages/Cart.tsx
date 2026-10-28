import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/money';
import type { CartItem, ShopProps } from '../types/shop';

function CartLine({ item }: { item: CartItem }) {
    const { market, text: t } = usePage<ShopProps>().props.shop;
    const form = useForm({ inventory_id: item.inventory_id, quantity: item.quantity });
    const remove = useForm({});
    function update(event: FormEvent) { event.preventDefault(); form.post('/panier/lignes', { preserveScroll: true }); }
    return <li data-reveal className="border-b border-black/10 py-7">
        <div className="flex gap-4 sm:gap-6">
            <div className="flex h-32 w-24 shrink-0 items-center justify-center border border-black/10 bg-white sm:h-40 sm:w-32">{item.image ? <img src={item.image.url} alt={item.image.alt} className="h-full w-full object-contain p-2" width="128" height="160" /> : <span className="font-display text-brand-ink italic">Nzhinu</span>}</div>
            <div className="min-w-0 flex-1">
                <h2 className="break-words font-display text-xl">{item.slug ? <Link href={`/produits/${item.slug}`}>{item.name}</Link> : item.name}</h2>
                {item.variant && <p className="mt-2 text-xs text-stone-600">{item.variant} · {item.location}</p>}
                {item.price_minor !== null && <p className="mt-3 text-sm">{money(item.price_minor, market)}</p>}
                {item.slug && item.available > 0 ? <form onSubmit={update} className="mt-4 flex flex-wrap items-end gap-3">
                    <label className="text-xs" htmlFor={`quantity-${item.inventory_id}`}>{t.quantity}<input id={`quantity-${item.inventory_id}`} type="number" className="shop-field mt-1 !w-20" min={1} max={Math.min(99, item.available)} required value={form.data.quantity} onChange={(e) => form.setData('quantity', Number(e.target.value))} /></label>
                    <button type="submit" className="secondary-button" disabled={form.processing}>{t.update}</button>
                </form> : <p className="mt-3 text-xs">{t.quantity} : {item.quantity}</p>}
                {(form.errors.quantity || form.errors.inventory_id) && <p className="shop-error mt-2" role="alert">{form.errors.quantity ?? form.errors.inventory_id}</p>}
                {!item.valid && <p className="shop-error mt-3" role="alert">{t.line_issue}</p>}
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <button type="button" className="text-xs underline underline-offset-4 disabled:opacity-50" disabled={remove.processing} onClick={() => remove.delete(`/panier/lignes/${item.inventory_id}`, { preserveScroll: true })}>{t.remove}</button>
                    {item.line_total_minor !== null && <p className="text-sm font-semibold">{money(item.line_total_minor, market)}</p>}
                </div>
            </div>
        </div>
    </li>;
}

type Props = { cart: { items: CartItem[]; subtotal_minor: number | null; has_issues: boolean; market_mismatch: boolean; stored_count: number } };

export default function Cart({ cart }: Props) {
    const { market, text: t } = usePage<ShopProps>().props.shop;
    const clear = useForm({});
    return <ShopLayout>
        <Head title={t.cart}><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <div className="shop-container py-10 sm:py-14">

            <h1 className="font-display text-4xl sm:text-5xl">{t.cart}</h1><p className="mt-4 text-sm text-stone-600">{t.cart_intro}</p>
            {cart.market_mismatch && <p className="shop-error mt-7 border-l-2 border-red-700 bg-white p-4" role="alert">{t.market_mismatch}</p>}
            {cart.items.length > 0 ? <div className="mt-7 grid gap-10 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div>
                    {cart.has_issues && <p className="shop-error border-l-2 border-red-700 bg-white p-4" role="alert">{t.cart_issues}</p>}
                    <ul>{cart.items.map((item) => <CartLine key={`${item.inventory_id}-${item.quantity}-${item.available}`} item={item} />)}</ul>
                    <Link href="/boutique" className="secondary-button mt-6">← {t.continue}</Link>
                </div>
                <aside data-reveal className="h-fit border border-black/10 bg-white p-7">
                    <h2 className="font-display text-2xl">{t.subtotal}</h2>
                    <p className="mt-5 text-2xl font-medium">{cart.subtotal_minor === null ? '—' : money(cart.subtotal_minor, market)}</p>
                    <p className="mt-4 text-xs leading-6 text-stone-600">{t.price_live} {t.fees_later}</p>
                    <div className="my-6 h-px bg-black/10" />
                    {!cart.has_issues && <Link href="/commander" className="primary-button w-full">{t.checkout} ↗</Link>}
                    <p className="mt-4 text-sm leading-7">{t.order_later}</p>
                    <p className="mt-4 text-xs leading-6 text-stone-600">{t.cart_notice}</p>
                </aside>
            </div> : <div className="mt-10 border border-dashed border-black/20 bg-white px-6 py-16 text-center"><h2 className="font-display text-2xl">{t.empty_cart}</h2><Link href="/boutique" className="primary-button mt-7">{t.continue} ↗</Link></div>}
            {cart.stored_count > 0 && <button type="button" className="mt-8 text-sm underline underline-offset-4 disabled:opacity-50" disabled={clear.processing} onClick={() => clear.delete('/panier')}>{t.clear_cart}</button>}
        </div>
    </ShopLayout>;
}
