import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, type FormEvent } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import ArrowIcon from '../Components/ArrowIcon';
import { WhatsAppIcon } from '../Components/WhatsAppContact';
import { money } from '../lib/money';
import type { CartItem, ShopProps } from '../types/shop';

type Props = { content: Record<string, string>; checkout: { request_key: string; cart: { items: CartItem[]; subtotal_minor: number };
    zones: { id: number; name: string; estimated_fee_minor: number | null }[]; points: { id: number; name: string; address: string }[] } };
type ContactField = 'customer_name' | 'phone' | 'email' | 'street' | 'street2' | 'city' | 'region' | 'postal_code';
const autocomplete: Record<ContactField, string> = { customer_name: 'name', phone: 'tel', email: 'email', street: 'address-line1', street2: 'address-line2', city: 'address-level2', region: 'address-level1', postal_code: 'postal-code' };

function MethodIcon({ pickup }: { pickup: boolean }) {
    return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="size-6" aria-hidden="true">{pickup ? <><path d="M3 10h18M4 10v11h16V10M2 10l3-7h14l3 7M9 21v-7h6v7" /><path d="M2 10a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0" /></> : <><path d="M2 5h12v12H2V5ZM14 9h4l4 4v4h-8M18 9v4h4" /><circle cx="6" cy="18" r="2" /><circle cx="18" cy="18" r="2" /></>}</svg>;
}

export default function Checkout({ checkout, content: t }: Props) {
    const { market, text: shopText, whatsapp_url } = usePage<ShopProps>().props.shop;
    const errorsSummary = useRef<HTMLDivElement>(null);
    const form = useForm({ request_key: checkout.request_key, customer_name: '', phone: '', email: '', method: 'delivery',
        pickup_point_id: '' as string | number, delivery_zone_id: '' as string | number, street: '', street2: '', city: '', region: '', postal_code: '' });
    useEffect(() => {
        // A refreshed response can withdraw an option; never submit a value the select can no longer show.
        if (form.data.delivery_zone_id && !checkout.zones.some((item) => item.id === Number(form.data.delivery_zone_id))) form.setData('delivery_zone_id', '');
        if (form.data.pickup_point_id && !checkout.points.some((item) => item.id === Number(form.data.pickup_point_id))) form.setData('pickup_point_id', '');
    }, [checkout.zones, checkout.points, form.data.delivery_zone_id, form.data.pickup_point_id]);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/commander', { onError: () => requestAnimationFrame(() => errorsSummary.current?.focus()) });
    }
    function changeMethod(method: 'delivery' | 'pickup') {
        form.setData('method', method);
        form.clearErrors('method', 'pickup_point_id', 'delivery_zone_id', 'street', 'street2', 'city', 'region', 'postal_code');
    }
    const zone = checkout.zones.find((item) => item.id === Number(form.data.delivery_zone_id));
    const point = checkout.points.find((item) => item.id === Number(form.data.pickup_point_id));
    const count = checkout.cart.items.reduce((total, item) => total + item.quantity, 0);
    function field(name: ContactField) {
        const required = ['customer_name', 'phone', 'street', 'city'].includes(name);
        const help = name === 'phone' ? t.phone_help : name === 'email' ? t.email_help : null;
        return <div key={name} className="min-w-0"><label htmlFor={`checkout-${name}`} className="block text-xs font-medium"><span id={`checkout-label-${name}`}>{t[name]}</span>{required && <span aria-hidden="true" className="ml-1 text-brand-ink">*</span>}</label>
            <input id={`checkout-${name}`} aria-labelledby={`checkout-label-${name}`} className="shop-field mt-2" value={form.data[name]} required={required} disabled={form.processing}
                type={name === 'email' ? 'email' : name === 'phone' ? 'tel' : 'text'} autoComplete={autocomplete[name]}
                maxLength={name === 'phone' || name === 'postal_code' ? 30 : name === 'city' || name === 'region' ? 100 : 255}
                aria-invalid={!!form.errors[name]} aria-describedby={form.errors[name] ? `checkout-error-${name}` : help ? `checkout-help-${name}` : undefined}
                onChange={(event) => form.setData(name, event.target.value)} />
            {help && <p id={`checkout-help-${name}`} className="mt-2 text-[11px] leading-6 text-stone-500">{help}</p>}
            {form.errors[name] && <p id={`checkout-error-${name}`} className="shop-error mt-2" role="alert">{form.errors[name]}</p>}
        </div>;
    }
    return <ShopLayout privatePage>
        <Head title={t.checkout_title}><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <section aria-labelledby="checkout-title" className="border-b border-black/10"><div className="shop-container pb-8 pt-6 sm:pb-10 sm:pt-8">
            <Link href="/panier" className="mb-6 inline-flex min-h-10 items-center gap-2 text-xs text-brand-ink"><span aria-hidden="true" className="rotate-180"><ArrowIcon /></span>{t.back_cart}</Link>
            <div className="flex flex-wrap items-end justify-between gap-6"><div><p className="mb-4 flex items-center gap-3 text-[10px] font-semibold tracking-[0.18em] uppercase text-brand-ink"><span className="h-px w-8 bg-brand-ink" aria-hidden="true" />{t.checkout_eyebrow}</p><h1 id="checkout-title" className="max-w-2xl font-display text-4xl tracking-tight sm:text-5xl">{t.checkout_title}</h1><p className="mt-4 max-w-xl text-sm leading-7 text-stone-600">{t.intro}</p></div>
                <ol aria-label={shopText.cart_steps} className="flex flex-wrap gap-x-5 gap-y-3 text-[11px]">{[shopText.cart, shopText.cart_step_contact, shopText.cart_step_confirmation].map((step, index) => <li key={step} aria-current={index === 1 ? 'step' : undefined} className={`flex items-center gap-2 ${index === 1 ? 'font-semibold text-brand-black' : 'text-stone-500'}`}><span aria-hidden="true" className={`flex size-7 items-center justify-center rounded-full border text-[10px] ${index === 1 ? 'border-brand-black bg-brand-black text-brand-gold' : 'border-black/15'}`}>0{index + 1}</span>{index === 0 ? <Link href="/panier" className="py-2 hover:text-brand-ink">{step}</Link> : step}</li>)}</ol>
            </div>
        </div></section>
        <div className="shop-container py-8 sm:py-12">
            {Object.keys(form.errors).length > 0 && <div ref={errorsSummary} tabIndex={-1} className="shop-error mb-6 border-l-2 border-red-700 bg-white p-5 outline-offset-4" role="alert"><p className="font-semibold">{t.errors_title}</p><ul className="mt-2 space-y-1">{[...new Set(Object.values(form.errors))].map((error) => <li key={error}>{error}</li>)}</ul></div>}
            <form id="checkout-form" onSubmit={submit} aria-label={t.checkout_title} aria-busy={form.processing} className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-10">
                <div className="min-w-0 space-y-6">
                    <fieldset className="min-w-0 border border-black/10 bg-white"><legend className="sr-only">{t.contact}</legend>
                        <div className="border-b border-black/10 p-5 sm:p-7"><div className="flex items-center gap-3"><span aria-hidden="true" className="font-display text-xl italic text-brand-ink">01</span><h2 className="font-display text-2xl">{t.contact}</h2></div><p className="mt-3 text-xs leading-6 text-stone-500">{t.contact_intro}</p></div>
                        <div className="space-y-5 p-5 sm:p-7">{field('customer_name')}<div className="grid gap-5 sm:grid-cols-2">{field('phone')}{field('email')}</div><p className="text-[11px] leading-6 text-stone-500">{t.required_help}</p></div>
                    </fieldset>
                    <fieldset className="min-w-0 border border-black/10 bg-white"><legend className="sr-only">{t.method}</legend>
                        <div className="border-b border-black/10 p-5 sm:p-7"><div className="flex items-center gap-3"><span aria-hidden="true" className="font-display text-xl italic text-brand-ink">02</span><h2 className="font-display text-2xl">{t.method}</h2></div><p className="mt-3 text-xs leading-6 text-stone-500">{t.method_intro}</p></div>
                        <div className="p-5 sm:p-7"><div className="grid gap-3 sm:grid-cols-2">{(['delivery', 'pickup'] as const).map((method) => {
                            const disabled = form.processing || (method === 'pickup' && checkout.points.length === 0);
                            return <label key={method} className={`relative min-w-0 border p-5 transition-colors ${form.data.method === method ? 'border-brand-ink bg-brand-gold/5' : 'border-black/15 hover:border-brand-ink/50'} ${disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`}><input type="radio" className="absolute right-4 top-5 size-4 accent-brand-ink" name="fulfillment-method" value={method} aria-label={t[method]} aria-describedby={`method-help-${method}`} checked={form.data.method === method} disabled={disabled} onChange={() => changeMethod(method)} /><span className="mb-4 flex size-10 items-center justify-center rounded-full border border-brand-gold/40 bg-brand-cream text-brand-ink"><MethodIcon pickup={method === 'pickup'} /></span><span className="block font-display text-xl">{t[method]}</span><span id={`method-help-${method}`} className="mt-2 block text-xs leading-6 text-stone-500">{t[`${method}_help`]}</span></label>;
                        })}</div>
                            {!checkout.points.length && <p className="mt-4 text-xs leading-6 text-stone-600">{t.no_pickup}</p>}
                            <div className="mt-6 border-t border-black/10 pt-6">
                                {form.data.method === 'delivery' ? <div className="space-y-5"><h3 className="font-display text-xl">{t.delivery_address_title}</h3>{field('street')}{field('street2')}<div className="grid gap-5 sm:grid-cols-2">{field('city')}{field('region')}</div>{field('postal_code')}
                                    <div><label htmlFor="checkout-zone" className="block text-xs font-medium">{t.zone}</label><select id="checkout-zone" className="shop-field mt-2" value={form.data.delivery_zone_id} disabled={form.processing} aria-invalid={!!form.errors.delivery_zone_id} aria-describedby={form.errors.delivery_zone_id ? 'checkout-error-zone' : 'checkout-zone-help'} onChange={(event) => form.setData('delivery_zone_id', event.target.value)}><option value="">{t.zone_unknown}</option>{checkout.zones.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select><p id="checkout-zone-help" className="mt-2 text-[11px] leading-6 text-stone-500">{t.zone_help}</p>{form.errors.delivery_zone_id && <p id="checkout-error-zone" className="shop-error mt-2" role="alert">{form.errors.delivery_zone_id}</p>}</div>
                                </div> : <div><h3 className="mb-5 font-display text-xl">{t.pickup_address_title}</h3><label htmlFor="checkout-point" className="block text-xs font-medium"><span id="checkout-label-point">{t.point}</span><span aria-hidden="true" className="ml-1 text-brand-ink">*</span></label><select id="checkout-point" aria-labelledby="checkout-label-point" className="shop-field mt-2" required value={form.data.pickup_point_id} disabled={form.processing} aria-invalid={!!form.errors.pickup_point_id} aria-describedby={form.errors.pickup_point_id ? 'checkout-error-point' : undefined} onChange={(event) => form.setData('pickup_point_id', event.target.value)}><option value="">{t.choose_point}</option>{checkout.points.map((item) => <option key={item.id} value={item.id}>{item.name} — {item.address}</option>)}</select>{form.errors.pickup_point_id && <p id="checkout-error-point" className="shop-error mt-2" role="alert">{form.errors.pickup_point_id}</p>}{point && <div className="mt-4 border-l-2 border-brand-gold bg-brand-cream p-4"><p className="text-[10px] font-semibold tracking-widest uppercase text-brand-ink">{t.selected_pickup}</p><p className="mt-2 text-sm font-medium">{point.name}</p><address className="mt-2 break-words text-xs not-italic leading-7 text-stone-600">{point.address}</address></div>}</div>}
                            </div>
                        </div>
                    </fieldset>
                </div>
                <aside aria-labelledby="checkout-summary-title" className="min-w-0 lg:sticky lg:top-28">
                    <div className="border border-black/10 bg-white p-5 sm:p-7"><div className="flex items-center justify-between gap-3"><h2 id="checkout-summary-title" className="font-display text-2xl">{t.summary}</h2><span className="text-xs text-stone-500">{count} {count === 1 ? shopText.cart_item : shopText.cart_items}</span></div><ul className="mt-5 space-y-4">{checkout.cart.items.map((item) => <li key={item.inventory_id} className="flex min-w-0 gap-3 border-t border-black/10 pt-4"><div className="flex h-20 w-16 shrink-0 items-center justify-center border border-black/5 bg-brand-cream">{item.image ? <img src={item.image.url} alt={item.image.alt} width="64" height="80" className="h-full w-full object-contain p-1" /> : <span className="font-display text-sm italic text-brand-ink">Nzhinu</span>}</div><div className="min-w-0"><h3 className="break-words text-sm font-medium">{item.name}</h3><p className="mt-1 break-words text-[11px] leading-5 text-stone-500">{item.variant} · × {item.quantity}</p><p className="mt-2 text-sm font-semibold">{money(item.line_total_minor!, market)}</p></div></li>)}</ul></div>
                    <div className="border-t-2 border-brand-gold bg-brand-black p-6 text-white sm:p-7"><dl><div><dt className="text-xs text-stone-300">{t.subtotal}</dt><dd className="mt-3 font-display text-3xl">{money(checkout.cart.subtotal_minor, market)}</dd></div><div className="mt-5 flex flex-wrap justify-between gap-3 border-t border-white/15 pt-5 text-xs leading-6"><dt className="text-stone-300">{form.data.method === 'delivery' ? t.estimate : t.pickup}</dt><dd aria-live="polite">{form.data.method === 'delivery' && zone?.estimated_fee_minor != null ? money(zone.estimated_fee_minor, market) : t.confirm_later}</dd></div></dl><p className="mt-4 text-[11px] leading-6 text-stone-400">{t.final_later}</p><button type="submit" className="primary-button mt-6 min-h-12 w-full" disabled={form.processing}>{form.processing ? t.submitting : t.submit}<span aria-hidden="true"><ArrowIcon /></span></button><p className="mt-5 text-xs leading-7 text-stone-300">{t.not_payment}</p></div>
                    <div className="border border-t-0 border-black/10 bg-white p-5 sm:p-7"><h3 className="font-display text-xl">{t.request_steps}</h3><ol className="mt-4 space-y-3 text-xs leading-7 text-stone-600"><li>{t.request_step_1}</li><li>{t.request_step_2}</li></ol>{whatsapp_url && <a href={whatsapp_url} target="_blank" rel="noopener noreferrer" className="mt-4 inline-flex min-h-11 items-center gap-2 text-xs text-brand-ink"><WhatsAppIcon className="size-5 shrink-0" />{t.whatsapp}<ArrowIcon /></a>}</div>
                </aside>
            </form>
        </div>
    </ShopLayout>;
}
