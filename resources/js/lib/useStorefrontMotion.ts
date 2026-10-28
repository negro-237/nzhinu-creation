import { useEffect, useRef, type RefObject } from 'react';

/** Progressive enhancement: content stays visible even without browser animation support. */
export function useStorefrontMotion(root: RefObject<HTMLDivElement | null>, url: string) {
    const seen = useRef(new WeakSet<Element>());
    useEffect(() => {
        const container = root.current;
        if (!container || !window.matchMedia || !window.IntersectionObserver || !Element.prototype.animate) return;
        const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
        const animations = new Set<Animation>();
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                const element = entry.target as HTMLElement;
                observer.unobserve(element);
                if (seen.current.has(element) || preference.matches) continue;
                seen.current.add(element);
                // Never animate an input's container while the customer is already interacting with it.
                if (element.contains(document.activeElement)) continue;
                const requested = Number(element.dataset.revealDelay ?? 0);
                const delay = Number.isFinite(requested) ? Math.max(0, Math.min(requested, 180)) : 0;
                const animation = element.animate([
                    { opacity: 0.15, transform: 'translateY(16px)' },
                    { opacity: 1, transform: 'translateY(0)' },
                ], { duration: 520, delay, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'backwards' });
                animations.add(animation);
                animation.onfinish = animation.oncancel = () => animations.delete(animation);
            }
        }, { threshold: 0.08 });
        function observe(element: Element) {
            if (element.matches('[data-reveal]') && !seen.current.has(element)) observer.observe(element);
            element.querySelectorAll('[data-reveal]').forEach((item) => { if (!seen.current.has(item)) observer.observe(item); });
        }
        const mutations = new MutationObserver((records) => {
            if (preference.matches) return;
            for (const record of records) for (const node of record.addedNodes) if (node instanceof Element) observe(node);
        });
        function configure() {
            observer.disconnect();
            mutations.disconnect();
            animations.forEach((animation) => animation.cancel());
            if (preference.matches) return;
            observe(container!);
            mutations.observe(container!, { childList: true, subtree: true });
        }
        configure();
        preference.addEventListener('change', configure);
        return () => {
            observer.disconnect();
            mutations.disconnect();
            animations.forEach((animation) => animation.cancel());
            preference.removeEventListener('change', configure);
        };
    }, [root, url]);
}
