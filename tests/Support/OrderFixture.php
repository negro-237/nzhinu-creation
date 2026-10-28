<?php

namespace Tests\Support;

use App\Enums\PaymentKind;
use App\Enums\StaffRole;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PickupPoint;
use App\Models\User;
use App\Services\GuestCheckout;
use App\Services\ManualPaymentService;
use App\Services\OrderWorkflow;
use Illuminate\Support\Str;

trait OrderFixture
{
    private function newOrder(array $f, bool $confirmed = true, string $method = 'delivery'): Order
    {
        $data = ['request_key' => (string) Str::uuid(), 'customer_name' => 'Client test', 'phone' => '+15145550123',
            'method' => $method, 'street' => 'Adresse fictive', 'city' => 'Ville test'];
        if ($method === 'pickup') {
            $point = PickupPoint::create(['market_id' => $f['ca']->id, 'stock_location_id' => $f['location']->id,
                'name' => 'Retrait test', 'address' => 'Point fictif', 'is_active' => true]);
            $data['pickup_point_id'] = $point->id;
        }
        $order = app(GuestCheckout::class)->place(bin2hex(random_bytes(32)), ['market_id' => $f['ca']->id, 'currency' => 'CAD',
            'quote' => [['inventory_id' => $f['inventory']->id, 'quantity' => 2, 'price_minor' => 5000]]], $data);

        return $confirmed ? app(OrderWorkflow::class)->confirm($f['actor'], $order->id, 0, ['shipping_fee' => '5', 'tax_amount' => '0', 'reason' => 'Appel confirmé test']) : $order;
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(StaffRole::Administrator);

        return $user;
    }

    private function receipt(User $actor, Order $order, string $amount = '105', PaymentKind $kind = PaymentKind::Receipt): OrderPayment
    {
        return app(ManualPaymentService::class)->record($actor, $order->id, (string) Str::uuid(), ['amount' => $amount,
            'method' => 'bank_transfer', 'reference' => (string) Str::uuid(), 'note' => 'Mouvement fictif de test'], $kind);
    }

    private function verifyPayment(User $admin, OrderPayment $payment): OrderPayment
    {
        return app(ManualPaymentService::class)->verify($admin, $payment->id, (string) Str::uuid(), ['source' => 'bank_statement', 'note' => 'Mouvement constaté sur le relevé bancaire de test']);
    }
}
