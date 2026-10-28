<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['currency' => Currency::class, 'is_active' => 'boolean'];
    }

    public function stockLocations(): HasMany
    {
        return $this->hasMany(StockLocation::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MarketPrice::class);
    }

    public function marketProducts(): HasMany
    {
        return $this->hasMany(MarketProduct::class);
    }
}
