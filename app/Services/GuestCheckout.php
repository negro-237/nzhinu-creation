<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderEventKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\GuestOrderRequest;
use App\Models\Inventory;
use App\Models\Market;
use App\Models\MarketPrice;
use App\Models\Order;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestCheckout
{
    public function __construct(private ShoppingCart $cart, private ReservationService $reservations, private DeliveryOptions $options) {}

    public function prepare(Session $session, Market $market): array
    {
        $cart = $this->cart->view($session, $market);
        if (! $cart['items'] || $cart['has_issues']) {
            throw ValidationException::withMessages(['cart' => __('orders.cart_error')]);
        }
        $session->put('checkout.owner', $session->get('checkout.owner', bin2hex(random_bytes(32))));
        $quote = collect($cart['items'])->map(fn ($item) => ['inventory_id' => $item['inventory_id'], 'quantity' => $item['quantity'], 'price_minor' => $item['price_minor']])->sortBy('inventory_id')->values()->all();
        $shape = hash('sha256', json_encode([$market->id, array_map(fn ($line) => [$line['inventory_id'], $line['quantity']], $quote)]));
        $drafts = $session->get('checkout.drafts', []);
        $existing = collect($drafts)->first(fn ($draft) => ! $draft['completed'] && $draft['shape'] === $shape);
        $key = $existing['request_key'] ?? (string) Str::uuid();
        $draft = ['request_key' => $key, 'market_id' => $market->id, 'currency' => $market->currency->value, 'quote' => $quote, 'shape' => $shape, 'completed' => false];
        $drafts[$key] = $draft;
        $session->put('checkout.drafts', array_slice($drafts, -10, null, true));
        $locationIds = Inventory::whereIn('id', array_column($quote, 'inventory_id'))->pluck('stock_location_id')->all();

        return ['request_key' => $key, 'cart' => $cart, ...$this->options->available($market, $locationIds)];
    }

    public function submit(Session $session, array $data): Order
    {
        $draft = $session->get('checkout.drafts.'.$data['request_key']);
        if (! $draft || ! $session->has('checkout.owner')) {
            throw ValidationException::withMessages(['request_key' => __('orders.reload')]);
        }
        if (! $draft['completed'] && ! Order::where('request_key', $data['request_key'])->exists()) {
            $current = $session->get('shop.cart');
            $lines = collect($current['items'] ?? [])->map(fn ($qty, $id) => [(int) $id, (int) $qty])->sortBy(fn ($line) => $line[0])->values()->all();
            if (hash('sha256', json_encode([$current['market_id'] ?? null, $lines])) !== $draft['shape']) {
                throw ValidationException::withMessages(['cart' => __('orders.cart_error')]);
            }
        }
        $order = $this->place($session->get('checkout.owner'), $draft, $data);
        if (! $draft['completed']) {
            $current = $session->get('shop.cart', []);
            $lines = collect($current['items'] ?? [])->map(fn ($qty, $id) => [(int) $id, (int) $qty])->sortBy(fn ($line) => $line[0])->values()->all();
            if (hash('sha256', json_encode([$current['market_id'] ?? null, $lines])) === $draft['shape']) {
                $session->forget('shop.cart');
            }
            $draft['completed'] = true;
            $session->put('checkout.drafts.'.$data['request_key'], $draft);
        }

        return $order;
    }

    /** Server-generated quote and session capability; never accept a quote from public request data. */
    public function place(string $owner, array $draft, array $input): Order
    {
        $data = Validator::make($input, GuestOrderRequest::inputRules())->validate();
        $data['phone'] = preg_replace('/[() .-]/', '', $data['phone']);
        if (strlen(ltrim($data['phone'], '+')) < 8 || strlen(ltrim($data['phone'], '+')) > 15) {
            throw ValidationException::withMessages(['phone' => __('orders.phone_error')]);
        }
        foreach (['customer_name', 'email', 'street', 'street2', 'city', 'region', 'postal_code'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }
        if ($data['method'] === 'pickup') {
            foreach (['delivery_zone_id', 'street', 'street2', 'city', 'region', 'postal_code'] as $field) {
                $data[$field] = null;
            }
        } else {
            $data['pickup_point_id'] = null;
        }
        $ownerHash = hash_hmac('sha256', $owner, config('app.key'));
        $payloadHash = hash_hmac('sha256', json_encode([$draft['market_id'], $draft['quote'], $data], JSON_THROW_ON_ERROR), config('app.key'));

        return DB::transaction(function () use ($draft, $data, $ownerHash, $payloadHash) {
            // Insert the unique idempotency row before any inventory lock. Contact fields use encrypted casts.
            $candidate = new Order([
                'request_key' => $data['request_key'], 'owner_hash' => $ownerHash, 'payload_hash' => $payloadHash,
                'reference' => (string) Str::uuid(), 'number' => 'NZ-'.now()->format('ymd').'-'.Str::upper(Str::random(10)),
                'market_id' => $draft['market_id'], 'currency' => $draft['currency'], 'method' => $data['method'],
                'customer_name' => $data['customer_name'], 'phone' => $data['phone'], 'email' => $data['email'],
                'delivery_address' => $data['method'] === 'delivery' ? collect($data)->only(['street', 'street2', 'city', 'region', 'postal_code'])->all() : null,
                'fulfillment_snapshot' => [], 'subtotal_minor' => 0,
                'status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Unpaid, 'fulfillment_status' => FulfillmentStatus::Unfulfilled,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            Order::query()->insertOrIgnore($candidate->getAttributes());
            $order = Order::where('request_key', $data['request_key'])->lockForUpdate()->firstOrFail();
            if (! hash_equals($order->owner_hash, $ownerHash) || ! hash_equals($order->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages(['request_key' => __('orders.replay_error')]);
            }
            if ($order->inventory_reservation_id) {
                return $order;
            }
            $market = Market::where('is_active', true)->whereIn('code', config('storefront.markets'))->find($draft['market_id']);
            if (! $market || $market->currency->value !== $draft['currency']) {
                throw ValidationException::withMessages(['cart' => __('orders.cart_error')]);
            }
            $subtotal = 0;
            $lines = [];
            $snapshots = [];
            $locationIds = [];
            $stocks = collect();
            $prices = collect();
            foreach (collect($draft['quote'])->sortBy('inventory_id') as $line) {
                $stocks[$line['inventory_id']] = Inventory::whereKey($line['inventory_id'])->lockForUpdate()->firstOrFail();
            }
            foreach ($stocks->pluck('product_variant_id')->unique()->sort()->values() as $variantId) {
                $prices[$variantId] = MarketPrice::where('market_id', $market->id)->where('product_variant_id', $variantId)->where('currency', $market->currency->value)->lockForUpdate()->first();
            }
            foreach (collect($draft['quote'])->sortBy('inventory_id') as $line) {
                $stock = $stocks[$line['inventory_id']];
                $variant = $stock->variant;
                $price = $prices[$variant->id];
                if (! $price || $price->amount_minor !== $line['price_minor']) {
                    throw ValidationException::withMessages(['cart' => __('orders.price_changed')]);
                }
                $total = $price->amount_minor * $line['quantity'];
                $subtotal += $total;
                $lines[] = ['inventory_id' => $stock->id, 'quantity' => $line['quantity']];
                $locationIds[] = $stock->stock_location_id;
                $snapshots[] = ['inventory_id' => $stock->id, 'stock_location_id' => $stock->stock_location_id, 'product_variant_id' => $variant->id,
                    'sku' => $variant->sku, 'product_name' => $variant->product->name, 'variant_label' => $variant->label, 'size' => $variant->size, 'color' => $variant->color,
                    'quantity' => $line['quantity'], 'unit_price_minor' => $price->amount_minor, 'line_total_minor' => $total, 'currency' => $market->currency->value];
            }
            $option = $this->options->snapshot($market, $data, $locationIds);
            $reservation = $this->reservations->reserveForOrder($order, $lines);
            $order->update(['inventory_reservation_id' => $reservation->id, 'subtotal_minor' => $subtotal,
                'delivery_zone_id' => $data['delivery_zone_id'] ?? null, 'pickup_point_id' => $data['pickup_point_id'] ?? null,
                'fulfillment_snapshot' => $option['snapshot'], 'shipping_estimate_minor' => $option['estimate']]);
            $order->items()->createMany($snapshots);
            $history = $order->histories()->create(['from_status' => null, 'to_status' => $order->status->value, 'payment_status' => $order->payment_status->value,
                'fulfillment_status' => $order->fulfillment_status->value, 'reason' => 'Demande invitée créée', 'created_at' => now()]);
            app(OrderNotifications::class)->enqueue($history, OrderEventKind::Created);

            return $order;
        }, attempts: 3);
    }
}
