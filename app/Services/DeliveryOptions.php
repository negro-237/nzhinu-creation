<?php

namespace App\Services;

use App\Models\DeliveryZone;
use App\Models\Market;
use App\Models\PickupPoint;
use App\Models\StockLocation;
use App\Models\User;
use App\Support\MoneyInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryOptions
{
    public function save(User $actor, string $model, array $data, ?Model $record = null): Model
    {
        abort_unless(in_array($model, [DeliveryZone::class, PickupPoint::class], true), 422);
        Gate::forUser($actor)->authorize($record ? 'update' : 'create', $record ?? $model);
        $rules = ['market_id' => ['required', 'integer', 'exists:markets,id'], 'name' => ['required', 'string', 'max:255',
            Rule::unique((new $model)->getTable(), 'name')->where('market_id', $data['market_id'] ?? 0)->ignore($record?->id)], 'is_active' => ['required', 'boolean']];
        $rules += $model === PickupPoint::class
            ? ['stock_location_id' => ['required', 'integer', 'exists:stock_locations,id'], 'address' => ['required', 'string', 'max:1000']]
            : ['estimate' => ['nullable', 'string', 'max:20']];
        $data = Validator::make($data, $rules)->validate();
        $market = Market::findOrFail($data['market_id']);
        if ($record && $record->market_id !== $market->id) {
            throw ValidationException::withMessages(['market_id' => 'Le marché est immuable.']);
        }
        if ($model === PickupPoint::class) {
            $location = StockLocation::findOrFail($data['stock_location_id']);
            if ($location->market_id !== $market->id || $location->is_placeholder || ! $location->is_active) {
                throw ValidationException::withMessages(['stock_location_id' => 'Choisissez un dépôt actif et validé de ce marché.']);
            }
        } else {
            try {
                $data['estimated_fee_minor'] = ! isset($data['estimate']) || $data['estimate'] === '' ? null : MoneyInput::toMinor($data['estimate'], $market->currency);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['estimate' => 'Montant invalide pour cette devise.']);
            }
            unset($data['estimate']);
        }

        return DB::transaction(function () use ($model, $record, $data) {
            if ($record) {
                $record = $model::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $record->update($data);

                return $record;
            }

            return $model::create($data);
        });
    }

    public function archive(User $actor, DeliveryZone|PickupPoint $record): bool
    {
        Gate::forUser($actor)->authorize('delete', $record);

        return (bool) $record->delete();
    }

    public function available(Market $market, array $locationIds): array
    {
        return [
            'zones' => DeliveryZone::where('market_id', $market->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'estimated_fee_minor']),
            'points' => count(array_unique($locationIds)) !== 1 ? [] : PickupPoint::where('market_id', $market->id)->where('is_active', true)
                ->where('stock_location_id', $locationIds[0])->whereHas('location', fn ($query) => $query->where('is_active', true)->where('is_placeholder', false)->whereNull('stock_locations.deleted_at'))
                ->orderBy('name')->get(['id', 'name', 'address']),
        ];
    }

    public function snapshot(Market $market, array $data, array $locationIds): array
    {
        if ($data['method'] === 'pickup') {
            $point = PickupPoint::where('market_id', $market->id)->where('is_active', true)->whereKey($data['pickup_point_id'] ?? 0)->lockForUpdate()->first();
            if (! $point || count(array_unique($locationIds)) !== 1 || $point->stock_location_id !== $locationIds[0]) {
                throw ValidationException::withMessages(['pickup_point_id' => __('orders.pickup_error')]);
            }

            return ['snapshot' => ['name' => $point->name, 'address' => $point->address, 'country' => $market->code], 'estimate' => null];
        }
        $zone = empty($data['delivery_zone_id']) ? null : DeliveryZone::where('market_id', $market->id)->where('is_active', true)->whereKey($data['delivery_zone_id'])->lockForUpdate()->first();
        if (! empty($data['delivery_zone_id']) && ! $zone) {
            throw ValidationException::withMessages(['delivery_zone_id' => __('orders.zone_error')]);
        }

        return ['snapshot' => ['name' => $zone?->name, 'country' => $market->code], 'estimate' => $zone?->estimated_fee_minor];
    }
}
