import type { Market } from '../types/shop';

// Display only. Prices and all line/subtotal calculations come from integer server amounts.
export function money(minor: number, market: Market): string {
    return new Intl.NumberFormat(market.code === 'CA' ? 'fr-CA' : 'fr-CM', {
        style: market.currency === 'XAF' ? 'decimal' : 'currency', currency: market.currency,
        minimumFractionDigits: market.currency === 'CAD' ? 2 : 0,
        maximumFractionDigits: market.currency === 'CAD' ? 2 : 0,
    }).format(minor / (market.currency === 'CAD' ? 100 : 1));
}
