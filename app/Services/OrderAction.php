<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderOperation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderAction
{
    /** Every caller first locks its order and authorizes the actor. */
    public function claim(User $actor, Order $order, string $key, string $kind, array $payload): OrderOperation
    {
        Validator::make(['request_key' => $key], ['request_key' => ['required', 'uuid']])->validate();
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        OrderOperation::insertOrIgnore(['request_key' => $key, 'order_id' => $order->id, 'actor_id' => $actor->id,
            'kind' => $kind, 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
        $operation = OrderOperation::where('request_key', $key)->lockForUpdate()->firstOrFail();
        if ($operation->order_id !== $order->id || $operation->actor_id !== $actor->id || $operation->kind !== $kind || ! hash_equals($operation->payload_hash, $hash)) {
            throw ValidationException::withMessages(['request_key' => 'Cette clé a déjà été utilisée pour une autre action.']);
        }

        return $operation;
    }
}
