<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['market_id', 'code', 'name', 'address', 'is_placeholder', 'is_active'];

    protected function casts(): array
    {
        return ['is_placeholder' => 'boolean', 'is_active' => 'boolean'];
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
