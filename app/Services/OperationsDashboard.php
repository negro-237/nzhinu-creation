<?php

namespace App\Services;

use App\Enums\StaffPermission;
use App\Models\Inventory;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderNotificationEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class OperationsDashboard
{
    public function report(User $actor): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::ViewDashboard->value);
        $markets = Market::orderBy('code')->get();
        $rows = [];
        foreach ($markets as $market) {
            // Group by the currency snapshot as well as market: never add different currencies.
            $groups = Order::where('market_id', $market->id)->select('currency')->selectRaw('COUNT(*) as total_orders')
                ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as awaiting_call")
                ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as awaiting_handover")
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
                ->selectRaw('SUM(verified_paid_minor) as paid, SUM(verified_refunded_minor) as refunded, SUM(verified_net_minor) as net')
                ->selectRaw("SUM(CASE WHEN verified_net_minor > 0 AND (status IN ('cancelled','expired') OR (total_minor IS NOT NULL AND verified_net_minor > total_minor)) THEN 1 ELSE 0 END) as review")
                ->groupBy('currency')->get();
            foreach ($groups as $group) {
                $rows[] = ['market' => $market->name, 'code' => $market->code, 'currency' => $group->currency,
                    'total' => (int) $group->total_orders, 'awaiting_call' => (int) $group->awaiting_call, 'awaiting_handover' => (int) $group->awaiting_handover,
                    'completed' => (int) $group->completed, 'paid' => (int) $group->paid, 'refunded' => (int) $group->refunded, 'net' => (int) $group->net, 'review' => (int) $group->review];
            }
        }
        $stocks = Inventory::with(['location', 'variant'])->whereHas('location', fn ($q) => $q->where('is_active', true)->where('is_placeholder', false))
            ->whereRaw('quantity_on_hand - quantity_reserved <= low_stock_threshold')->orderBy('id')->limit(20)->get();

        return ['markets' => $rows, 'low_stocks' => $stocks,
            'pending_events' => OrderNotificationEvent::whereNull('expanded_at')->count(),
            'pending_notifications' => OrderNotificationDelivery::where('status', 'pending')->count(),
            'failed_notifications' => OrderNotificationDelivery::where('status', 'failed')->count()];
    }
}
