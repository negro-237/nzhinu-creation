<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockLocationService
{
    public function save(User $actor, array $data, ?StockLocation $location = null): StockLocation
    {
        Gate::forUser($actor)->authorize($location ? 'update' : 'create', $location ?? StockLocation::class);
        $data = Validator::make($data, [
            'market_id' => ['required', 'integer', 'exists:markets,id'],
            'code' => ['required', 'string', 'max:50', 'regex:/\A[A-Z0-9_-]+\z/', Rule::unique('stock_locations', 'code')->ignore($location?->id)],
            'name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'], 'is_placeholder' => ['required', 'boolean'],
        ])->validate();
        if ($data['is_active'] && ($data['is_placeholder'] || empty(trim($data['address'] ?? '')))) {
            throw ValidationException::withMessages(['address' => 'Une adresse réelle validée est obligatoire pour activer ce lieu.']);
        }

        return DB::transaction(function () use ($data, $location) {
            if ($location) {
                $location = StockLocation::whereKey($location->id)->lockForUpdate()->firstOrFail();
                if ($location->market_id !== (int) $data['market_id']) {
                    throw ValidationException::withMessages(['market_id' => 'Le marché d’un lieu existant ne peut pas changer.']);
                }
                $location->update($data);

                return $location;
            }

            return StockLocation::create($data);
        }, attempts: 3);
    }

    public function archive(User $actor, StockLocation $location): bool
    {
        Gate::forUser($actor)->authorize('delete', $location);

        return DB::transaction(function () use ($location) {
            $location = StockLocation::whereKey($location->id)->lockForUpdate()->firstOrFail();
            foreach ($location->inventories()->orderBy('id')->pluck('id') as $id) {
                $inventory = Inventory::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($inventory->quantity_on_hand > 0 || $inventory->quantity_reserved > 0) {
                    throw ValidationException::withMessages(['location' => 'Un lieu contenant du stock ne peut pas être archivé.']);
                }
            }

            return (bool) $location->delete();
        }, attempts: 3);
    }
}
