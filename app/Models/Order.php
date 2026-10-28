<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\FulfillmentMethod;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['owner_hash', 'payload_hash', 'customer_name', 'phone', 'email', 'delivery_address'];

    protected function casts(): array
    {
        return ['currency' => Currency::class, 'method' => FulfillmentMethod::class, 'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class, 'fulfillment_status' => FulfillmentStatus::class,
            'customer_name' => 'encrypted', 'phone' => 'encrypted', 'email' => 'encrypted', 'delivery_address' => 'encrypted:array',
            'fulfillment_snapshot' => 'array', 'payment_due_at' => 'datetime', 'version' => 'integer',
            'subtotal_minor' => 'integer', 'total_minor' => 'integer', 'shipping_fee_minor' => 'integer', 'tax_minor' => 'integer',
            'verified_paid_minor' => 'integer', 'verified_refunded_minor' => 'integer', 'verified_net_minor' => 'integer'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'inventory_reservation_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(OrderFulfillment::class);
    }

    public function getFinancialReviewRequiredAttribute(): bool
    {
        return $this->verified_net_minor > 0 && (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Expired], true)
            || ($this->total_minor !== null && $this->verified_net_minor > $this->total_minor));
    }
}
