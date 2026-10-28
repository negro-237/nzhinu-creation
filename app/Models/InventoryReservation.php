<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => ReservationStatus::class, 'expires_at' => 'datetime', 'cycle' => 'integer'];
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReservationHistory::class);
    }
}
