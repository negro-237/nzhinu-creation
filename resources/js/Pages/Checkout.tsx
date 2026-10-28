import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ShopLayout from '../Layouts/ShopLayout';
import { money } from '../lib/money';
import type { CartItem, ShopProps } from '../types/shop';

type Props = { content: Record<string, string>; checkout: { request_key: string; cart: { items: CartItem[]; subtotal_minor: number };
    zones: { id: number; name: string; estimated_fee_minor: number | null }[]; points: { id: number; name: string; address: string }[] } };

export default function Checkout({ checkout, content: t }: Props) {
    const { market } = usePage<ShopProps>().props.shop;
    const form = useForm({ request_key: checkout.request_key, customer_name: '', phone: '', email: '', method: 'delivery',
        pickup_point_id: '' as string | number, delivery_zone_id: '' as string | number, street: '', street2: '', city: '', region: '', postal_code: '' });
    function submit(event: FormEvent) { event.preventDefault(); form.post('/commander'); }
    const zone = checkout.zones.find((item) => item.id === Number(form.data.delivery_zone_id));
    const fields = (names: ('customer_name' | 'phone' | 'email' | 'street' | 'street2' | 'city' | 'region' | 'postal_code')[]) => names.map((name) => <label key={name} className="block text-sm" htmlFor={`checkout-${name}`}>{t[name]}
        <input id={`checkout-${name}`} className="shop-field mt-2" value={form.data[name]} required={['customer_name', 'phone', 'street', 'city'].includes(name)}
            type={name === 'email' ? 'email' : name === 'phone' ? 'tel' : 'text'} autoComplete={({customer_name:'name', phone:'tel', email:'email', street:'address-line1', street2:'address-line2', city:'address-level2', region:'address-level1', postal_code:'postal-code'})[name]}
            maxLength={name === 'phone' ? 30 : name === 'postal_code' ? 30 : 255} aria-invalid={!!form.errors[name]} onChange={(e) => form.setData(name, e.target.value)} />
        {form.errors[name] && <span className="shop-error" role="alert">{form.errors[name]}</span>}
    </label>);
    return <ShopLayout privatePage><Head title={t.checkout_title}><meta head-key="robots" name="robots" content="noindex,nofollow" /></Head>
        <div className="shop-container py-10 sm:py-14">
            <Link href="/panier" className="text-sm underline underline-offset-4">← {t.back_cart}</Link>

            <h1 className="mt-3 font-display text-4xl sm:text-5xl">{t.checkout_title}</h1><p className="mt-4 max-w-2xl text-sm leading-7 text-stone-600">{t.intro}</p>
            {Object.keys(form.errors).length > 0 && <div className="shop-error mt-6 border-l-2 border-red-700 bg-white p-4" role="alert">{[...new Set(Object.values(form.errors))].map((error) => <p key={error}>{error}</p>)}</div>}
            <form onSubmit={submit} className="mt-10 grid gap-10 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div className="space-y-8">
                    <fieldset data-reveal className="space-y-5 border border-black/10 bg-white p-6"><legend className="px-2 font-display text-2xl">{t.contact}</legend>{fields(['customer_name', 'phone', 'email'])}</fieldset>
                    <fieldset data-reveal className="space-y-5 border border-black/10 bg-white p-6"><legend className="px-2 font-display text-2xl">{t.method}</legend>
                        <div className="flex flex-wrap gap-6">{['delivery', 'pickup'].map((method) => <label key={method} className="flex items-center gap-2 text-sm"><input type="radio" className="accent-brand-black" name="fulfillment-method" value={method} checked={form.data.method === method} disabled={method === 'pickup' && checkout.points.length === 0} onChange={() => form.setData('method', method)} />{t[method]}</label>)}</div>
                        {!checkout.points.length && <p className="text-xs leading-6 text-stone-600">{t.no_pickup}</p>}

                        {form.data.method === 'delivery' ? <>{fields(['street', 'street2', 'city', 'region', 'postal_code'])}
                            <label className="block text-sm">{t.zone}<select className="shop-field mt-2" value={form.data.delivery_zone_id} onChange={(e) => form.setData('delivery_zone_id', e.target.value)}><option value="">{t.zone_unknown}</option>{checkout.zones.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                        </> : <label className="block text-sm">{t.point}<select className="shop-field mt-2" required value={form.data.pickup_point_id} onChange={(e) => form.setData('pickup_point_id', e.target.value)}><option value="">{t.choose_point}</option>{checkout.points.map((item) => <option key={item.id} value={item.id}>{item.name} — {item.address}</option>)}</select></label>}
                    </fieldset>
                </div>
                <aside data-reveal className="h-fit border border-black/10 bg-white p-6">
                    <h2 className="font-display text-2xl">{t.summary}</h2><ul className="mt-5 space-y-4">{checkout.cart.items.map((item) => <li key={item.inventory_id} className="border-b border-black/10 pb-4 text-sm"><p className="font-medium">{item.name}</p><p className="mt-1 text-xs text-stone-600">{item.variant} · {item.location} · × {item.quantity}</p><p className="mt-2">{money(item.line_total_minor!, market)}</p></li>)}</ul>
                    <p className="mt-6 text-sm">{t.subtotal}</p><p className="mt-2 text-2xl font-medium">{money(checkout.cart.subtotal_minor, market)}</p>
                    {form.data.method === 'delivery' && zone?.estimated_fee_minor != null && <p className="mt-4 text-sm">{t.estimate} : {money(zone.estimated_fee_minor, market)}</p>}
                    <p className="mt-4 text-xs leading-6 text-stone-600">{t.final_later}</p><button type="submit" className="primary-button mt-7 w-full" disabled={form.processing}>{t.submit} ↗</button>
                    <p className="mt-4 text-xs leading-6 text-stone-600">{t.not_payment}</p>
                </aside>
            </form>
        </div>
    </ShopLayout>;
}
