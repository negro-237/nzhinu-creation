import { usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { useEffect, useState, type RefObject } from 'react';
import type { ShopProps } from '../types/shop';

export function WhatsAppIcon({ className = 'size-6' }: { className?: string }) {
    return <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden="true" focusable="false">
        <path fillRule="evenodd" d="M12 2a10 10 0 0 0-8.65 15L2 22l5.14-1.3A10 10 0 1 0 12 2Zm0 1.8a8.2 8.2 0 1 1-4.3 15.18l-.32-.2-2.82.72.75-2.74-.22-.33A8.2 8.2 0 0 1 12 3.8Zm-3.22 3.6c-.18-.4-.36-.41-.53-.42h-.45c-.17 0-.45.06-.69.32-.24.26-.91.89-.91 2.16s.94 2.5 1.07 2.67c.13.17 1.83 2.79 4.43 3.91 2.16.93 2.6.75 3.07.7.47-.04 1.52-.62 1.74-1.22.22-.6.22-1.11.16-1.22-.07-.11-.24-.17-.5-.3l-1.78-.83c-.24-.09-.41-.13-.59.13-.17.26-.67.83-.82 1-.15.18-.3.2-.56.07s-1.1-.41-2.1-1.31c-.78-.7-1.31-1.57-1.46-1.83-.15-.26-.02-.4.11-.53l.39-.45c.13-.15.17-.26.26-.43.09-.18.04-.33-.02-.46L8.78 7.4Z" clipRule="evenodd" />
    </svg>;
}

export default function WhatsAppContact({ floatingContainer }: { floatingContainer: RefObject<HTMLDivElement | null> }) {
    const { whatsapp_url, text: t } = usePage<ShopProps>().props.shop;
    const [floating, setFloating] = useState(false);
    useEffect(() => {
        // Only float outside the content when the viewport has room beside the 1280px storefront.
        const query = window.matchMedia('(min-width: 1440px)');
        const update = () => setFloating(query.matches);
        update();
        query.addEventListener('change', update);
        return () => query.removeEventListener('change', update);
    }, []);
    if (!whatsapp_url) return null;
    const link = <a href={whatsapp_url} target="_blank" rel="noopener noreferrer" aria-label={t.whatsapp_floating} data-floating={floating}
        className={`shop-whatsapp group z-30 flex shrink-0 items-center justify-center rounded-full border border-brand-gold bg-brand-black text-brand-gold transition-colors hover:bg-brand-gold hover:text-brand-black ${floating ? 'fixed size-14 shadow-xl' : 'relative size-11'}`}>
        <span className="shop-whatsapp-pulse pointer-events-none absolute inset-0 rounded-full border border-brand-gold" aria-hidden="true" />
        <WhatsAppIcon className={`relative ${floating ? 'size-8' : 'size-6'}`} />
        <span className="pointer-events-none absolute right-full mr-3 hidden whitespace-nowrap rounded-sm bg-brand-black px-4 py-2 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 sm:block">{t.whatsapp}</span>
    </a>;
    // A portal keeps fixed positioning outside the header's backdrop-filter containing block.
    return floating && floatingContainer.current ? createPortal(link, floatingContainer.current) : link;
}
