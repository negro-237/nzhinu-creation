<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentEntryStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['kind' => PaymentKind::class, 'status' => PaymentEntryStatus::class, 'method' => PaymentMethod::class,
            'currency' => Currency::class, 'amount_minor' => 'integer', 'received_at' => 'datetime', 'verified_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
