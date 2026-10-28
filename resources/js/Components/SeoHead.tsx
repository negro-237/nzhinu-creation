import { Head, usePage } from '@inertiajs/react';
import type { ShopProps } from '../types/shop';

export default function SeoHead() {
    const { seo } = usePage<ShopProps>().props;
    if (!seo) return null;
    const nonce = typeof document !== 'undefined' ? document.querySelector<HTMLMetaElement>('meta[name="csp-nonce"]')?.content : undefined;
    return <Head title={seo.head_title}>
        <meta head-key="description" name="description" content={seo.description} />
        <meta head-key="robots" name="robots" content={seo.robots} />
        <link head-key="canonical" rel="canonical" href={seo.canonical} />
        <meta head-key="og:title" property="og:title" content={seo.title} />
        <meta head-key="og:description" property="og:description" content={seo.description} />
        <meta head-key="og:url" property="og:url" content={seo.canonical} />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:site_name" property="og:site_name" content="Nzhinu Création" />
        <meta head-key="og:locale" property="og:locale" content="fr_CM" />
        <meta head-key="og:image" property="og:image" content={seo.image} />
        <meta head-key="og:image:alt" property="og:image:alt" content={seo.image_alt} />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" content={seo.title} />
        <meta head-key="twitter:description" name="twitter:description" content={seo.description} />
        <meta head-key="twitter:image" name="twitter:image" content={seo.image} />
        {seo.structured_json && <script head-key="structured-data" type="application/ld+json" nonce={nonce} dangerouslySetInnerHTML={{ __html: seo.structured_json }} />}
    </Head>;
}
