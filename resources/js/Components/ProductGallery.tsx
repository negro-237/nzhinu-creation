import { useEffect, useId, useRef, useState, type KeyboardEvent, type TouchEvent } from 'react';
import { usePage } from '@inertiajs/react';
import type { ProductDetail, ShopProps } from '../types/shop';

export default function ProductGallery({ images }: { images: ProductDetail['images'] }) {
    const { text: t } = usePage<ShopProps>().props.shop;
    const [index, setIndex] = useState(0);
    const [expanded, setExpanded] = useState(false);
    const [zoomed, setZoomed] = useState(false);
    const dialog = useRef<HTMLDialogElement>(null);
    const gallery = useRef<HTMLElement>(null);
    const closeButton = useRef<HTMLButtonElement>(null);
    const thumbnails = useRef<HTMLDivElement>(null);
    const start = useRef<{ x: number; y: number } | null>(null);
    const trackId = useId();
    const multiple = images.length > 1;
    const largeId = `${trackId}-large`;
    function select(position: number) { setZoomed(false); setIndex(position); }
    function move(step: number) { setZoomed(false); setIndex((current) => (current + step + images.length) % images.length); }
    useEffect(() => {
        if (!expanded || !dialog.current) return;
        const element = dialog.current;
        const overflow = document.body.style.overflow;
        element.showModal();
        document.body.style.overflow = 'hidden';
        closeButton.current?.focus({ preventScroll: true });
        return () => {
            element.close(); document.body.style.overflow = overflow;
            gallery.current?.querySelector<HTMLButtonElement>('button[aria-haspopup="dialog"][tabindex="0"]')?.focus({ preventScroll: true });
        };
    }, [expanded]);
    useEffect(() => {
        const strip = thumbnails.current;
        const active = strip?.children[index] as HTMLElement | undefined;
        if (!strip || !active) return;
        strip.scrollTo({ left: active.offsetLeft - (strip.clientWidth - active.clientWidth) / 2,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }, [index]);
    function touchStart(event: TouchEvent) {
        const touch = event.touches[0];
        start.current = !zoomed && event.touches.length === 1 ? { x: touch.clientX, y: touch.clientY } : null;
    }
    function touchEnd(event: TouchEvent) {
        const origin = start.current; start.current = null;
        if (!multiple || !origin || !event.changedTouches[0] || zoomed) return;
        const touch = event.changedTouches[0]; const dx = touch.clientX - origin.x; const dy = touch.clientY - origin.y;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) move(dx < 0 ? 1 : -1);
    }
    function arrow(previous: boolean, controls = trackId) {
        return <button type="button" aria-label={previous ? t.previous_image : t.next_image} aria-controls={controls} onClick={() => move(previous ? -1 : 1)} className={`absolute top-1/2 ${previous ? 'left-3' : 'right-3'} flex size-11 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-brand-gold bg-brand-black text-brand-gold shadow-md transition-colors hover:bg-brand-gold hover:text-brand-black focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold`}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="pointer-events-none block size-5 shrink-0" aria-hidden="true" focusable="false"><path d={previous ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6'} /></svg></button>;
    }
    function keyboard(event: KeyboardEvent) {
        if (!multiple) return;
        if (event.key === 'ArrowRight') move(1);
        else if (event.key === 'ArrowLeft') move(-1);
        else if (event.key === 'Home') select(0);
        else if (event.key === 'End') select(images.length - 1);
        else return;
        event.preventDefault();
    }
    function modalKeyboard(event: KeyboardEvent) {
        event.stopPropagation();
        if (event.key === 'Tab') {
            const controls = dialog.current?.querySelectorAll<HTMLButtonElement>('button:not([disabled])');
            if (!controls?.length) return;
            const first = controls[0]; const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            return;
        }
        keyboard(event);
    }
    return <section ref={gallery} aria-label={t.gallery} aria-roledescription={multiple ? t.carousel : undefined} className="min-w-0" onKeyDown={keyboard}>
        <div className="relative overflow-hidden border border-black/10 bg-[#f4f1eb]">
            <div id={trackId} tabIndex={multiple ? 0 : undefined} aria-label={t.gallery_navigation} className="aspect-[4/5] outline-offset-[-3px] focus-visible:outline-2 focus-visible:outline-brand-gold" style={{ touchAction: 'pan-y pinch-zoom' }}
                onTouchStart={touchStart}
                onTouchCancel={() => { start.current = null; }}
                onTouchEnd={touchEnd}>
                {images.length ? <div className="flex h-full transition-transform duration-300 ease-out motion-reduce:transition-none" style={{ transform: `translateX(-${index * 100}%)` }}>
                    {images.map((image, position) => <div key={`${image.url}-${position}`} className="h-full w-full shrink-0" aria-hidden={position !== index} inert={position !== index}>
                        <button type="button" aria-label={t.expand_image} tabIndex={position === index ? 0 : -1} aria-haspopup="dialog" onClick={() => setExpanded(true)} className="relative block h-full w-full cursor-zoom-in focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-brand-gold">
                            <img src={image.url} alt={image.alt} draggable={false} fetchPriority={position === 0 ? 'high' : 'auto'} loading={position === 0 ? 'eager' : 'lazy'} width="640" height="800" className="h-full w-full object-contain" />
                            <span className="absolute right-3 bottom-3 inline-flex items-center gap-2 border border-black/10 bg-white/95 px-3 py-2 text-xs"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-4" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5" /></svg>{t.enlarge}</span>
                        </button>
                    </div>)}
                </div> : <div className="flex h-full items-center justify-center text-center"><div><p className="font-display text-6xl italic text-brand-ink">Nzhinu</p><p className="mt-5 text-sm text-stone-500">{t.photo_pending}</p></div></div>}
            </div>
            {multiple && <>
                {arrow(true)}{arrow(false)}
            </>}
        </div>
        {multiple && <>
            <div className="mt-3 flex flex-wrap justify-between gap-2 text-xs text-stone-600"><p role="status" aria-live="polite" aria-atomic="true">{t.image} {index + 1} {t.of} {images.length}</p><p>{t.gallery_hint}</p></div>
            <div ref={thumbnails} className="relative mt-3 flex gap-3 overflow-x-auto p-1" aria-label={t.gallery_thumbnails}>{images.map((image, position) => <button key={`${image.url}-${position}`} type="button" aria-pressed={position === index} aria-label={`${t.show_image} ${position + 1} : ${image.alt}`} aria-controls={trackId} onClick={() => select(position)} className={`size-20 shrink-0 cursor-pointer border-2 bg-white p-1 transition-colors hover:border-brand-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-gold ${position === index ? 'border-brand-gold' : 'border-black/10'}`}><img src={image.url} alt="" width="80" height="80" loading="lazy" className="h-full w-full object-contain" /></button>)}</div>
        </>}
        {images[index] && <dialog ref={dialog} aria-label={t.image_viewer} onCancel={() => setExpanded(false)} onClose={() => { setExpanded(false); setZoomed(false); }} onClick={(event) => { if (event.target === event.currentTarget) setExpanded(false); }} onKeyDown={modalKeyboard} className="fixed inset-0 m-auto h-fit max-h-[94dvh] w-[92vw] max-w-6xl overflow-hidden border border-black/10 bg-brand-cream p-0 text-brand-black shadow-2xl backdrop:bg-black/80">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-black/10 bg-white px-3 py-2 sm:px-5">
                <p className="text-xs" role="status" aria-live="polite">{t.image} {index + 1} {t.of} {images.length}</p>
                <div className="flex items-center gap-2"><button type="button" aria-label={zoomed ? t.reset_zoom : t.zoom_image} aria-pressed={zoomed} onClick={() => setZoomed(!zoomed)} className="secondary-button min-h-11 cursor-pointer !px-3 !text-xs">{zoomed ? '−' : '+'} {zoomed ? t.reset_zoom : t.zoom_image}</button><button ref={closeButton} type="button" aria-label={t.close_viewer} onClick={() => setExpanded(false)} className="secondary-button min-h-11 cursor-pointer gap-2 !px-3 !text-xs">{t.close}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="size-4" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg></button></div>
            </div>
            <div className="relative">
                <div id={largeId} className="h-[64dvh] overflow-auto" onTouchStart={touchStart} onTouchEnd={touchEnd} onTouchCancel={() => { start.current = null; }}>
                    <img src={images[index].url} alt={images[index].alt} draggable={false} onClick={() => setZoomed(!zoomed)} className={zoomed ? 'block h-auto w-[200%] max-w-none cursor-zoom-out' : 'block h-full w-full cursor-zoom-in object-contain'} />
                </div>
                {multiple && <>{arrow(true, largeId)}{arrow(false, largeId)}</>}
            </div>
            <p className="max-h-20 overflow-auto border-t border-black/10 bg-white px-5 py-3 text-center text-xs leading-6 text-stone-600">{images[index].alt}</p>
        </dialog>}
    </section>;
}
