<?php

namespace App\Services;

use App\Models\InventoryOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventoryOperationService
{
    /** Claim the request key before acquiring reservation/inventory locks. */
    public function claim(User $actor, string $key, string $kind, array $payload): InventoryOperation
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Inventory operations require a transaction.');
        }
        Validator::make(['request_key' => $key], ['request_key' => ['required', 'uuid']])->validate();
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        InventoryOperation::query()->insertOrIgnore([
            'request_key' => strtolower($key), 'kind' => $kind, 'payload_hash' => $hash,
            'actor_id' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $operation = InventoryOperation::where('request_key', strtolower($key))->lockForUpdate()->firstOrFail();
        if ($operation->kind !== $kind || $operation->payload_hash !== $hash || $operation->actor_id !== $actor->id) {
            throw ValidationException::withMessages(['request_key' => 'Cette clé a déjà été utilisée pour une autre opération.']);
        }

        return $operation;
    }
}
