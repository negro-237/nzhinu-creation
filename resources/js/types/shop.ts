export type Market = { code: 'CA' | 'CM'; name: string; currency: 'CAD' | 'XAF' };
export type Image = { url: string; alt: string };
export type Taxonomy = { name: string; slug: string };
export type ProductCardData = {
    id: number; name: string; slug: string; category: Taxonomy;
    price_minor: number; in_stock: boolean; image: Image | null;
};
export type ProductDetail = ProductCardData & {
    similar_products: ProductCardData[];
    description: string | null; meta_description: string; images: Image[]; collections: Taxonomy[];
    variants: { id: number; sku: string; label: string; size: string | null; color: string | null;
        price_minor: number; stocks: { id: number; location: string; available: number }[] }[];
};
export type CartItem = {
    inventory_id: number; name: string; slug: string | null; variant: string | null; location: string | null;
    available: number; price_minor: number | null; image: Image | null;
    quantity: number; valid: boolean; line_total_minor: number | null;
};
export type ShopProps = {
    seo?: { head_title: string; title: string; description: string; canonical: string; robots: string; image: string; image_alt: string; structured_json: string | null;
        category: { name: string; slug: string; description: string | null } | null };
    shop: { market: Market; markets: Market[]; boutique: { name: string; address: string; country: string }; cart_count: number; text: Record<string, string>; pages: { title: string; slug: string }[]; whatsapp_url: string | null;
        contact_email: string | null; categories: { name: string; slug: string; icon: string }[];
        social_links: { network: string; label: string; url: string }[] };
    flash: { success: string | null }; [key: string]: unknown;
};
