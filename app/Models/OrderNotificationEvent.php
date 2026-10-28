<?php

namespace App\Models;

use App\Enums\OrderEventKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotificationEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['kind' => OrderEventKind::class, 'expanded_at' => 'datetime'];
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(OrderHistory::class, 'order_history_id');
    }
}
