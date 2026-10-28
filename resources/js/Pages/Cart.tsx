import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import ArrowIcon from '../Components/ArrowIcon';
import { WhatsAppIcon } from '../Components/WhatsAppContact';
import { money } from '../lib/money';
import type { CartItem, ShopProps } from '../types/shop';

function BagIcon() {
    return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" className="size-9" aria-hidden="true"><path d="M5 7h14l1 14H4L5 7Z" /><path d="M8 9V6a4 4 0 0 1 8 0v3" /></svg>;
}

type EditingCallback = (id: number, editing: boolean) => void;
function CartLine({ item, clearing, onEditingChange }: { item: CartItem; clearing: boolean; onEditingChange: EditingCallback }) {
    const { market, text: t } = usePage<ShopProps>().props.shop;
    const form = useForm({ inventory_id: item.inventory_id, quantity: item.quantity });
    const remove = useForm({});
    const busy = form.processing || remove.processing || clearing;
    const maximum = Math.min(99, item.available);
    const validQuantity = Number.isInteger(form.data.quantity) && form.data.quantity >= 1;
    useEffect(() => {
        onEditingChange(item.inventory_id, form.isDirty || form.processing || remove.processing);
    }, [item.inventory_id, form.isDirty, form.processing, remove.processing, onEditingChange]);
    useEffect(() => () => onEditingChange(item.inventory_id, false), [item.inventory_id, onEditingChange]);
    function setQuantity(quantity: number) { form.setData('quantity', quantity); form.clearErrors('quantity'); }
    function update(event: FormEvent) { event.preventDefault(); form.post('/panier/lignes', { preserveScroll: true }); }
    const photo = item.image ? <img src={item.image.url} alt={item.image.alt} className="h-full w-full object-contain p-2" width="128" height="160" /> : <span className="font-display text-lg italic text-brand-ink sm:text-2xl">Nzhinu</span>;
    return <li data-reveal className={`min-w-0 border bg-white p-4 sm:p-6 ${item.valid ? 'border-black/10' : 'border-red-700/25'}`}>
        <article aria-labelledby={`cart-item-${item.inventory_id}`} className="grid grid-cols-[80px_minmax(0,1fr)] gap-x-4 gap-y-5 sm:grid-cols-[112px_minmax(0,1fr)] sm:gap-x-6">
            <div className="flex aspect-[4/5] items-center justify-center overflow-hidden border border-black/5 bg-[#f4f1eb]">{item.slug ? <Link href={`/produits/${item.slug}`} aria-label={`${t.view_product} : ${item.name}`} className="flex h-full w-full items-center justify-center">{photo}</Link> : photo}</div>
            <div className="min-w-0">
                <p className="mb-2 text-[10px] tracking-widest uppercase text-brand-ink">{t.hero_selection}</p>
                <h3 id={`cart-item-${item.inventory_id}`} className="break-words font-display text-xl leading-snug sm:text-2xl">{item.slug ? <Link href={`/produits/${item.slug}`} className="hover:text-brand-ink">{item.name}</Link> : item.name}</h3>
                {item.variant && <p className="mt-2 break-words text-xs leading-6 text-stone-500">{item.variant}</p>}
                {item.location && <p className="break-words text-[11px] leading-6 text-stone-500">{item.location}</p>}
                {item.price_minor !== null && <p className="mt-3 text-sm font-medium"><span className="mr-2 text-[10px] font-normal text-stone-500">{t.cart_unit_price}</span>{money(item.price_minor, market)}</p>}
            </div>
            <div className="col-span-2 min-w-0 sm:col-span-1 sm:col-start-2">
                {item.slug && item.available > 0 ? <form onSubmit={update} aria-label={`${t.cart_edit_line} : ${item.name}`}>
                    <label className="mb-2 block text-[11px] font-medium" htmlFor={`quantity-${item.inventory_id}`}>{t.quantity}</label>
                    <div className="flex flex-wrap items-center gap-3"><div className="inline-flex items-center border border-black/20">
                        <button type="button" aria-label={t.decrease_quantity} disabled={busy || !validQuantity || form.data.quantity <= 1} onClick={() => setQuantity(Math.max(1, form.data.quantity - 1))} className="flex size-11 items-center justify-center text-lg hover:bg-brand-cream disabled:cursor-not-allowed disabled:text-stone-300">−</button>
                        <input id={`quantity-${item.inventory_id}`} type="number" className="shop-field !w-16 !border-y-0 !border-x-black/10 !px-1 !text-center" min={1} max={maximum} required disabled={busy} value={form.data.quantity} onChange={(event) => setQuantity(Number(event.target.value))} aria-invalid={!!form.errors.quantity} aria-describedby={form.errors.quantity ? `cart-error-${item.inventory_id}` : form.isDirty ? `cart-draft-${item.inventory_id}` : undefined} />
                        <button type="button" aria-label={t.increase_quantity} disabled={busy || !validQuantity || form.data.quantity >= maximum} onClick={() => setQuantity(Math.min(maximum, form.data.quantity + 1))} className="flex size-11 items-center justify-center text-lg hover:bg-brand-cream disabled:cursor-not-allowed disabled:text-stone-300">+</button>
                    </div><button type="submit" className="secondary-button min-h-11 !text-xs" disabled={busy || !form.isDirty}>{t.update}</button></div>
                    {form.isDirty && <p id={`cart-draft-${item.inventory_id}`} className="mt-2 text-xs leading-6 text-brand-ink">{t.cart_pending_line}</p>}
                </form> : <p className="text-xs">{t.quantity} : {item.quantity}</p>}
                {(form.errors.quantity || form.errors.inventory_id) && <p id={`cart-error-${item.inventory_id}`} className="shop-error mt-2" role="alert">{form.errors.quantity ?? form.errors.inventory_id}</p>}
                {!item.valid && <p className="shop-error mt-3" role="alert">{t.line_issue}</p>}
            </div>
            <div className="col-span-2 flex flex-wrap items-center justify-between gap-4 border-t border-black/10 pt-4">
                <button type="button" aria-label={`${t.remove} : ${item.name}`} className="inline-flex min-h-11 items-center gap-2 text-xs text-stone-500 hover:text-brand-black disabled:opacity-50" disabled={busy} onClick={() => remove.delete(`/panier/lignes/${item.inventory_id}`, { preserveScroll: true })}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-4" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7" /></svg>{t.remove}</button>
                <div className="text-right"><p className="text-[10px] text-stone-500">{t.cart_line_total}</p><p className="mt-1 font-display text-xl">{item.line_total_minor === null ? '—' : money(item.line_total_minor, market)}</p></div>
            </div>
        </article>
    </li>;
}

type Props = { cart: { items: CartItem[]; subtotal_minor: number | null; has_issues: boolean; market_mismatch: boolean; stored_count: number } };

export default function Cart({ cart }: Props) {
    const { market, text: t, whatsapp_url } = usePage<ShopProps>().props.shop;
    const clear = useForm({});
    const [editing, setEditing] = useState(new Set<number>());
    const onEditingChange = useCallback<EditingCallback>((id, pending) => setEditing((current) => {
        if (current.has(id) === pending) return current;
        const next = new Set(current);
        if (pending) next.add(id); else next.delete(id);
        return next;
    }), []);
    const pending = cart.items.some((item) => editing.has(item.inventory_id)) || clear.processing;
    return <ShopLayout>
        <Head title={t.cart}><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section className="border-b border-black/10" aria-labelledby="cart-title"><div className="shop-container pb-8 pt-6 sm:pb-10 sm:pt-8">
            <nav aria-label={t.catalog_breadcrumb} className="mb-6 flex items-center gap-3 text-xs text-stone-500"><Link href="/" className="py-2 hover:text-brand-ink">{t.home}</Link><span aria-hidden="true">/</span><span aria-current="page">{t.cart}</span></nav>
            <div className="flex flex-wrap items-end justify-between gap-6"><div><p className="mb-4 flex items-center gap-3 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink"><span className="h-px w-8 bg-brand-ink" aria-hidden="true" />{t.cart_eyebrow}</p><h1 id="cart-title" className="font-display text-4xl tracking-tight sm:text-6xl">{t.cart}</h1><p className="mt-4 max-w-xl text-sm leading-7 text-stone-600">{t.cart_intro}</p></div>
                <ol aria-label={t.cart_steps} className="flex flex-wrap gap-x-5 gap-y-3 text-[11px] sm:gap-x-6">{[t.cart, t.cart_step_contact, t.cart_step_confirmation].map((step, index) => <li key={step} aria-current={index === 0 ? 'step' : undefined} className={`flex items-center gap-2 ${index === 0 ? 'font-semibold text-brand-black' : 'text-stone-500'}`}><span aria-hidden="true" className={`flex size-7 shrink-0 items-center justify-center rounded-full border text-[10px] ${index === 0 ? 'border-brand-black bg-brand-black text-brand-gold' : 'border-black/15'}`}>0{index + 1}</span>{step}</li>)}</ol>
            </div>
        </div></section>
        <div className="shop-container py-8 sm:py-12">
            {cart.market_mismatch && <p className="shop-error mb-6 border-l-2 border-red-700 bg-white p-4" role="alert">{t.market_mismatch}</p>}
            {cart.items.length > 0 ? <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-10">
                <section aria-labelledby="cart-selection-title" className="min-w-0">
                    <div className="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 id="cart-selection-title" className="font-display text-2xl">{t.cart_selection}</h2><p className="text-xs text-stone-500" role="status" aria-live="polite">{cart.stored_count} {cart.stored_count === 1 ? t.cart_item : t.cart_items}</p></div>
                    {cart.has_issues && <p className="shop-error mb-5 border-l-2 border-red-700 bg-white p-4" role="alert">{t.cart_issues}</p>}
                    <ul className="space-y-4">{cart.items.map((item) => <CartLine key={`${item.inventory_id}-${item.quantity}-${item.available}`} item={item} clearing={clear.processing} onEditingChange={onEditingChange} />)}</ul>
                    <div className="mt-5 flex flex-wrap items-center justify-between gap-3"><Link href="/boutique" className="inline-flex min-h-11 items-center gap-2 py-2 text-xs text-brand-ink"><span aria-hidden="true" className="rotate-180"><ArrowIcon /></span>{t.continue}</Link><button type="button" className="min-h-11 py-2 text-xs text-stone-500 underline underline-offset-4 disabled:opacity-50" disabled={clear.processing} onClick={() => clear.delete('/panier')}>{t.clear_cart}</button></div>
                </section>
                <aside aria-labelledby="cart-summary-title" className="min-w-0 lg:sticky lg:top-28">
                    <div className="border-t-2 border-brand-gold bg-brand-black p-6 text-white sm:p-7"><p className="text-[10px] tracking-[0.18em] uppercase text-brand-gold">{t.hero_selection}</p><h2 id="cart-summary-title" className="mt-4 font-display text-2xl">{t.cart_summary}</h2>
                        <dl className="mt-7"><div className="border-b border-white/15 pb-5"><dt className="text-xs leading-6 text-stone-300">{t.subtotal}</dt><dd className="mt-2 font-display text-3xl" aria-live="polite" aria-atomic="true">{cart.subtotal_minor === null ? '—' : money(cart.subtotal_minor, market)}</dd></div><div className="flex flex-wrap justify-between gap-3 pt-5 text-xs leading-6"><dt className="text-stone-300">{t.cart_delivery}</dt><dd>{t.cart_to_confirm}</dd></div></dl>
                        <p className="mt-4 text-[11px] leading-6 text-stone-400">{t.price_live} {t.fees_later}</p>
                        {pending && <p id="cart-pending" role="status" className="mt-5 border-l-2 border-brand-gold pl-3 text-xs leading-6 text-brand-gold">{t.cart_pending}</p>}
                        {!cart.has_issues && !pending ? <Link href="/commander" className="primary-button mt-6 min-h-12 w-full">{t.checkout}<span aria-hidden="true"><ArrowIcon /></span></Link> : <button type="button" disabled aria-describedby={pending ? 'cart-pending' : undefined} className="primary-button mt-6 min-h-12 w-full">{t.checkout}<ArrowIcon /></button>}
                        <p className="mt-5 text-xs leading-7 text-stone-300">{t.order_later}</p>
                    </div>
                    <div className="border border-t-0 border-black/10 bg-white p-6 sm:p-7"><h3 className="font-display text-xl">{t.cart_next}</h3><p className="mt-3 text-xs leading-7 text-stone-600">{t.cart_next_text}</p><p className="mt-4 border-t border-black/10 pt-4 text-[11px] leading-6 text-stone-500">{t.cart_notice}</p>
                        {whatsapp_url && <a href={whatsapp_url} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex min-h-11 items-center gap-2 text-xs text-brand-ink"><WhatsAppIcon className="size-5 shrink-0" />{t.cart_help}<ArrowIcon /></a>}
                    </div>
                </aside>
            </div> : <section aria-labelledby="empty-cart-title" className="grid overflow-hidden border border-black/10 bg-white lg:grid-cols-[1.5fr_1fr]"><div className="px-6 py-12 text-center sm:px-10 sm:py-16"><span className="mx-auto mb-6 flex size-20 items-center justify-center rounded-full border border-brand-gold/40 bg-brand-cream text-brand-ink"><BagIcon /></span><h2 id="empty-cart-title" className="font-display text-3xl">{t.empty_cart}</h2><p className="mx-auto mt-4 max-w-md text-sm leading-7 text-stone-600">{t.cart_empty_text}</p><Link href="/boutique" className="primary-button mt-7">{t.continue}<ArrowIcon /></Link></div><div className="flex flex-col justify-center border-l-2 border-brand-gold bg-brand-black p-7 text-white sm:p-10"><p className="text-[10px] tracking-[0.18em] uppercase text-brand-gold">{t.hero_selection}</p><p className="mt-5 font-display text-3xl italic leading-tight">{t.cart_empty_signature}</p><p className="mt-5 text-xs leading-7 text-stone-300">{t.footer_intro}</p></div></section>}
            {cart.items.length === 0 && cart.stored_count > 0 && <button type="button" className="mt-6 min-h-11 text-xs underline underline-offset-4 disabled:opacity-50" disabled={clear.processing} onClick={() => clear.delete('/panier')}>{t.clear_cart}</button>}
        </div>
    </ShopLayout>;
}
