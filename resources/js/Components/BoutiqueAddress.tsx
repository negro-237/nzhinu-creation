import ArrowIcon from './ArrowIcon';
import { usePage } from '@inertiajs/react';
import type { ShopProps } from '../types/shop';

export default function BoutiqueAddress({ showTitle = true }: { showTitle?: boolean }) {
    const { boutique, text: t } = usePage<ShopProps>().props.shop;
    const destination = encodeURIComponent(`${boutique.address}, ${boutique.country}`);
    return <div className="space-y-3 text-sm">
        {showTitle && <p className="font-semibold">{t.boutique_address}</p>}
        <address className="not-italic leading-7">{boutique.address}</address>
        <a href={`https://www.google.com/maps/dir/?api=1&destination=${destination}`} target="_blank" rel="noopener noreferrer" aria-label={`${t.directions} — ${t.new_tab}`} className="inline-block underline underline-offset-4">{t.directions} <ArrowIcon /></a>
    </div>;
}
