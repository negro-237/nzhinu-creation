<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(2);
            $table->timestamp('low_stock_alerted_at')->nullable();
            $table->timestamps();
            $table->unique(['stock_location_id', 'product_variant_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_reserved_check CHECK (quantity_reserved <= quantity_on_hand)');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER inventories_check_$event BEFORE $event ON inventories
                    WHEN NEW.quantity_on_hand < 0 OR NEW.quantity_reserved < 0 OR NEW.low_stock_threshold < 0
                        OR NEW.quantity_reserved > NEW.quantity_on_hand
                    BEGIN SELECT RAISE(ABORT, 'Invalid inventory quantities'); END");
            }
        }

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['active', 'released', 'expired'])->default('active');
            $table->unsignedInteger('cycle')->default(1);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['status', 'expires_at', 'id']);
        });
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unique(['inventory_reservation_id', 'inventory_id'], 'reservation_inventory_unique');
        });
        Schema::create('inventory_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('kind', 30);
            $table->char('payload_hash', 64);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('type', ['adjustment', 'reservation', 'release', 'expiry']);
            $table->integer('quantity_delta');
            $table->integer('reserved_delta');
            $table->unsignedInteger('on_hand_after');
            $table->unsignedInteger('reserved_after');
            $table->unsignedInteger('threshold_after');
            $table->text('reason');
            $table->timestamp('created_at');
        });
        Schema::create('reservation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->unsignedInteger('cycle');
            $table->text('reason');
            $table->timestamp('created_at');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'reservation_histories', 'stock_movements', 'inventory_operations', 'reservation_items', 'inventory_reservations', 'inventories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
