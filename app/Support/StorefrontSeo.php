<?php

namespace App\Support;

class StorefrontSeo
{
    /** Initial HTML metadata without requiring a separate Node SSR server. */
    public static function fromPage(array $page): array
    {
        $props = $page['props'];
        $text = $props['shop']['text'];
        $component = $page['component'];
        $title = match ($component) {
            'Welcome' => $props['content']['title'].' '.$props['content']['title_accent'],
            'Catalog' => $text['catalog'],
            'Product' => $props['product']['name'],
            'Checkout' => $props['content']['checkout_title'],
            'OrderConfirmation' => ($props['order']['status_code'] ?? 'pending') === 'pending' ? $props['content']['confirmation_title'] : $props['content']['tracking_title'],
            'ContentPage' => $props['page']['title'],
            default => $text['cart'],
        };

        return [
            'title' => $title.' | '.$text['brand'],
            'description' => match ($component) {
                'Welcome' => $props['content']['intro'],
                'Product' => $props['product']['meta_description'],
                'Catalog' => $text['catalog_intro'],
                'ContentPage' => $props['page']['meta_description'],
                default => $text['cart_intro'],
            },
            'canonical' => $props['canonical'],
            'robots' => in_array($component, ['Cart', 'Checkout', 'OrderConfirmation']) ? 'noindex,nofollow'
                : ($component === 'Catalog' && array_diff(array_keys($props['filters']), ['page']) ? 'noindex,follow' : 'index,follow'),
            'product' => $component === 'Product' ? $props['product'] : null,
        ];
    }
}
