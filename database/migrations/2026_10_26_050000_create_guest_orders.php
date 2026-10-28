<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->unsignedBigInteger('requested_by')->nullable()->change());
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('estimated_fee_minor')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['market_id', 'name']);
        });
        Schema::create('pickup_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('address');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['market_id', 'name']);
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->char('owner_hash', 64);
            $table->char('payload_hash', 64);
            $table->uuid('reference')->unique();
            $table->string('number', 40)->unique();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->enum('currency', ['CAD', 'XAF']);
            $table->foreignId('inventory_reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pickup_point_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('method', ['delivery', 'pickup']);
            // Encrypted casts require TEXT rather than fixed-length plaintext columns.
            $table->text('customer_name');
            $table->text('phone');
            $table->text('email')->nullable();
            $table->text('delivery_address')->nullable();
            $table->json('fulfillment_snapshot');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('shipping_estimate_minor')->nullable();
            $table->unsignedBigInteger('shipping_fee_minor')->nullable();
            $table->unsignedBigInteger('tax_minor')->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->enum('fulfillment_status', ['unfulfilled', 'packed', 'shipped', 'collected'])->default('unfulfilled');
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('sku', 100);
            $table->string('product_name');
            $table->string('variant_label', 100);
            $table->string('size', 50)->nullable();
            $table->string('color', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->enum('currency', ['CAD', 'XAF']);
            $table->unique(['order_id', 'inventory_id']);
        });
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('payment_status');
            $table->string('fulfillment_status');
            $table->text('reason');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['order_histories', 'order_items', 'orders', 'pickup_points', 'delivery_zones'] as $name) {
            Schema::dropIfExists($name);
        }
        // Older staff-only schema requires an actor. Disposable guest reservations have none.
        // Keep the nullable column on rollback to preserve any historical guest reservation.
    }
};
