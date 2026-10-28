<?php

namespace App\Services;

use App\Enums\StaffPermission;
use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private InventoryOperationService $operations, private InventoryLedger $ledger) {}

    public function create(User $actor, array $data): Inventory
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageInventory->value);
        $data = Validator::make($data, [
            'stock_location_id' => ['required', 'integer'], 'product_variant_id' => ['required', 'integer'],
            'quantity_on_hand' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'], 'request_key' => ['required', 'uuid'],
        ])->validate();
        $payload = [(int) $data['stock_location_id'], (int) $data['product_variant_id'], (int) $data['quantity_on_hand'], (int) $data['low_stock_threshold'], $data['reason']];

        return DB::transaction(function () use ($actor, $data, $payload) {
            $operation = $this->operations->claim($actor, $data['request_key'], 'initialization', $payload);
            if ($operation->inventory_id) {
                return Inventory::findOrFail($operation->inventory_id);
            }
            $location = StockLocation::whereKey($data['stock_location_id'])->lockForUpdate()->firstOrFail();
            if ($location->is_placeholder || ! $location->address) {
                throw ValidationException::withMessages(['stock_location_id' => 'Validez une adresse réelle avant de saisir le stock.']);
            }
            ProductVariant::findOrFail($data['product_variant_id']);
            if (Inventory::where('stock_location_id', $location->id)->where('product_variant_id', $data['product_variant_id'])->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'Un stock existe déjà pour ce lieu et cette variante.']);
            }
            $inventory = Inventory::create([
                'stock_location_id' => $location->id, 'product_variant_id' => $data['product_variant_id'],
                'quantity_on_hand' => 0, 'quantity_reserved' => 0, 'low_stock_threshold' => $data['low_stock_threshold'],
            ]);
            $this->ledger->record($inventory, StockMovementType::Adjustment, (int) $data['quantity_on_hand'], 0, $data['reason'], $actor->id);
            $operation->update(['inventory_id' => $inventory->id]);

            return $inventory;
        }, attempts: 3);
    }

    public function adjust(User $actor, int $inventoryId, array $data): Inventory
    {
        Gate::forUser($actor)->authorize(StaffPermission::ManageInventory->value);
        $data = Validator::make($data, [
            'quantity_delta' => ['required', 'integer', 'min:-1000000000', 'max:1000000000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'], 'request_key' => ['required', 'uuid'],
        ])->validate();
        $threshold = isset($data['low_stock_threshold']) ? (int) $data['low_stock_threshold'] : null;

        return DB::transaction(function () use ($actor, $inventoryId, $data, $threshold) {
            $operation = $this->operations->claim($actor, $data['request_key'], 'adjustment', [$inventoryId, (int) $data['quantity_delta'], $threshold, $data['reason']]);
            $inventory = Inventory::whereKey($inventoryId)->lockForUpdate()->firstOrFail();
            if ($operation->inventory_id) {
                return $inventory;
            }
            if ($inventory->location->trashed()) {
                throw ValidationException::withMessages(['inventory' => 'Ce lieu est archivé.']);
            }
            if ($threshold !== null) {
                $inventory->low_stock_threshold = $threshold;
            }
            $this->ledger->record($inventory, StockMovementType::Adjustment, (int) $data['quantity_delta'], 0, $data['reason'], $actor->id);
            $operation->update(['inventory_id' => $inventory->id]);

            return $inventory;
        }, attempts: 3);
    }
}
