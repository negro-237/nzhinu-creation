<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = ['category_id', 'name', 'slug', 'description', 'status', 'version'];

    protected function casts(): array
    {
        return ['status' => ProductStatus::class, 'version' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(CulturalCollection::class, 'collection_product');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function marketProducts(): HasMany
    {
        return $this->hasMany(MarketProduct::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /** Catalog eligibility only: physical stock is handled in phase 3. */
    public function scopeAvailableIn(Builder $query, Market $market): Builder
    {
        return $query->where('status', ProductStatus::Published)
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true)->whereNull('deleted_at'))
            ->whereHas('marketProducts', fn (Builder $query) => $query->where('market_id', $market->id)
                ->where('is_available', true)->whereHas('market', fn (Builder $query) => $query->where('is_active', true)))
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true)
                ->whereHas('prices', fn (Builder $query) => $query->where('market_id', $market->id)->where('currency', $market->currency->value)));
    }
}
