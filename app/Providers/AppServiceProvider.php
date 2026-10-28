<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\CulturalCollection;
use App\Models\DeliveryZone;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StoreBanner;
use App\Policies\CatalogPolicy;
use App\Policies\ContentPolicy;
use App\Policies\DeliveryOptionPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\InventoryReservationPolicy;
use App\Policies\OrderPaymentPolicy;
use App\Policies\OrderPolicy;
use App\Policies\StockMovementPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Tests\Support\EnforcedCsrf;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Real browser tests must exercise CSRF even though their isolated server uses APP_ENV=testing.
        if ($this->app->environment('testing') && getenv('E2E_TESTING') === '1') {
            $this->app->bind(ValidateCsrfToken::class, EnforcedCsrf::class);
            $this->app->bind(VerifyCsrfToken::class, EnforcedCsrf::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('cart-mutations', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('catalog-search', fn (Request $request) => Limit::perMinute(90)->by($request->ip()));
        RateLimiter::for('checkout-placement', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('order-receipt', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        foreach ([CmsPage::class, StoreBanner::class] as $model) {
            Gate::policy($model, ContentPolicy::class);
        }
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderPayment::class, OrderPaymentPolicy::class);
        foreach ([DeliveryZone::class, PickupPoint::class] as $model) {
            Gate::policy($model, DeliveryOptionPolicy::class);
        }
        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(InventoryReservation::class, InventoryReservationPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        foreach ([Category::class, CulturalCollection::class, Product::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }
    }
}
