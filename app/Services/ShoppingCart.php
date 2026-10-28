<?php

namespace App\Services;

use App\Models\Market;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

class ShoppingCart
{
    public function __construct(private StorefrontCatalog $catalog) {}

    public function count(Session $session): int
    {
        return array_sum($session->get('shop.cart.items', []));
    }

    public function put(Session $session, Market $market, int $inventoryId, int $quantity): void
    {
        $cart = $session->get('shop.cart', ['market_id' => $market->id, 'items' => []]);
        if (! empty($cart['items']) && $cart['market_id'] !== $market->id) {
            throw ValidationException::withMessages(['inventory_id' => __('shop.cart_market_error')]);
        }
        $stock = $this->catalog->stocks($market, [$inventoryId])->get($inventoryId);
        if (! $stock) {
            throw ValidationException::withMessages(['inventory_id' => __('shop.unavailable_error')]);
        }
        if ($quantity < 1 || $quantity > 99 || $quantity > $stock['available']) {
            throw ValidationException::withMessages(['quantity' => __('shop.quantity_error')]);
        }
        if (! isset($cart['items'][$inventoryId]) && count($cart['items']) >= 50) {
            throw ValidationException::withMessages(['inventory_id' => __('shop.cart_limit_error')]);
        }
        $cart['market_id'] = $market->id;
        $cart['items'][$inventoryId] = $quantity; // Absolute quantity: duplicate HTTP retries have the same effect.
        $session->put('shop.cart', $cart);
        $session->put('shop.market', $market->code);
    }

    public function remove(Session $session, int $inventoryId): void
    {
        // Removing an already removed line is an idempotent no-op, including in another session.
        $session->forget('shop.cart.items.'.$inventoryId);
    }

    public function clear(Session $session): void
    {
        $session->forget('shop.cart');
    }

    public function view(Session $session, Market $market): array
    {
        $stored = $session->get('shop.cart.items', []);
        if ($stored && $session->get('shop.cart.market_id') !== $market->id) {
            return ['items' => [], 'subtotal_minor' => null, 'has_issues' => true, 'market_mismatch' => true, 'stored_count' => $this->count($session)];
        }
        $stocks = $this->catalog->stocks($market, array_keys($stored));
        $items = [];
        $subtotal = 0;
        $issues = false;
        foreach ($stored as $id => $quantity) {
            $stock = $stocks->get($id);
            $valid = $stock && $quantity <= $stock['available'];
            $issues = $issues || ! $valid;
            $total = $stock ? $stock['price_minor'] * $quantity : null;
            $subtotal += $valid ? $total : 0;
            $items[] = [...($stock ?? [
                'inventory_id' => (int) $id, 'name' => __('shop.unavailable_item'), 'slug' => null,
                'variant' => null, 'location' => null, 'available' => 0, 'price_minor' => null, 'image' => null,
            ]), 'quantity' => $quantity, 'valid' => (bool) $valid, 'line_total_minor' => $total];
        }

        return ['items' => $items, 'subtotal_minor' => $issues ? null : $subtotal, 'has_issues' => $issues, 'market_mismatch' => false, 'stored_count' => $this->count($session)];
    }
}
