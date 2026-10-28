<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPrice extends Model
{
    use HasFactory;

    protected $fillable = ['market_id', 'product_variant_id', 'amount_minor', 'currency'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'currency' => Currency::class];
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
