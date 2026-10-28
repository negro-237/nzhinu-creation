<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SearchIndexController;
use App\Http\Controllers\StorefrontController;
use App\Http\Middleware\PrivateOrderPage;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->block()->name('home');
Route::get('/sitemap.xml', [SearchIndexController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SearchIndexController::class, 'robots'])->name('seo.robots');
Route::get('/boutique', [StorefrontController::class, 'catalog'])->middleware('throttle:catalog-search')->block()->name('shop.catalog');
Route::get('/pages/{slug}', [StorefrontController::class, 'page'])->where('slug', '[a-z0-9-]+')->block()->name('shop.page');
Route::get('/produits/{slug}', [StorefrontController::class, 'product'])->block()->name('shop.product')->where('slug', '[a-z0-9-]+');
Route::get('/panier', [CartController::class, 'show'])->block()->name('shop.cart');
Route::post('/marche', [StorefrontController::class, 'selectMarket'])->middleware('throttle:cart-mutations')->block()->name('shop.market');
Route::post('/panier/lignes', [CartController::class, 'put'])->middleware('throttle:cart-mutations')->block()->name('shop.cart.put');
Route::delete('/panier/lignes/{inventoryId}', [CartController::class, 'remove'])->middleware('throttle:cart-mutations')->where('inventoryId', '[1-9][0-9]{0,17}')->block()->name('shop.cart.remove');
Route::delete('/panier', [CartController::class, 'clear'])->middleware('throttle:cart-mutations')->block()->name('shop.cart.clear');
Route::middleware(PrivateOrderPage::class)->group(function () {
    Route::get('/commander', [CheckoutController::class, 'show'])->block()->name('checkout.show');
    Route::post('/commander', [CheckoutController::class, 'place'])->middleware('throttle:checkout-placement')->block(30, 10)->name('checkout.place');
    Route::get('/commandes/{reference}', [CheckoutController::class, 'confirmation'])->whereUuid('reference')->middleware(['signed', 'throttle:order-receipt'])->block()->name('orders.show');
});
