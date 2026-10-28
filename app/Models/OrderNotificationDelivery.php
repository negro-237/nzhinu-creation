<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotificationDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'sent_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(OrderNotificationEvent::class, 'order_notification_event_id');
    }
}
