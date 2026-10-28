import { Head } from '@inertiajs/react';
import ShopLayout from '../Layouts/ShopLayout';

export default function ContentPage({ page }: { page: { title: string; body: string; meta_description: string } }) {
    return <ShopLayout><Head title={page.title}><meta head-key="description" name="description" content={page.meta_description} /></Head>
        <article data-reveal className="shop-container max-w-3xl py-12 sm:py-20"><h1 className="font-display text-4xl sm:text-5xl">{page.title}</h1>
            <div className="mt-8 whitespace-pre-line break-words text-base leading-8 text-stone-700">{page.body}</div>
        </article>
    </ShopLayout>;
}
