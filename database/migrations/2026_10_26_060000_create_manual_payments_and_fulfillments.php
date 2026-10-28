<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired', 'completed'])->default('pending')->change();
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded', 'partially_refunded'])->default('unpaid')->change();
            $table->unsignedBigInteger('verified_paid_minor')->default(0);
            $table->unsignedBigInteger('verified_refunded_minor')->default(0);
            $table->unsignedBigInteger('verified_net_minor')->default(0);
        });
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->enum('status', ['active', 'released', 'expired', 'consumed'])->default('active')->change());
        Schema::table('stock_movements', fn (Blueprint $table) => $table->enum('type', ['adjustment', 'reservation', 'release', 'expiry', 'handover'])->change());
        Schema::table('order_histories', fn (Blueprint $table) => $table->json('context')->nullable());
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['receipt', 'refund']);
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->unsignedBigInteger('amount_minor');
            $table->enum('currency', ['CAD', 'XAF']);
            $table->string('method', 30);
            $table->string('reference', 120);
            $table->text('note');
            $table->timestamp('received_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->enum('verification_source', ['bank_statement', 'provider_record', 'cash_count'])->nullable();
            $table->text('verification_note')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['market_id', 'kind', 'method', 'reference'], 'payment_reference_unique');
            $table->index(['order_id', 'id']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_payments ADD CONSTRAINT payment_verified_check CHECK (status <> 'verified' OR (verified_by IS NOT NULL AND verified_at IS NOT NULL AND verification_source IS NOT NULL))");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER payment_check_$event BEFORE $event ON order_payments WHEN NEW.amount_minor < 0 OR (NEW.status = 'verified' AND (NEW.verified_by IS NULL OR NEW.verified_at IS NULL OR NEW.verification_source IS NULL)) BEGIN SELECT RAISE(ABORT, 'Invalid payment verification'); END");
            }
        }
        Schema::create('order_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 40);
            $table->char('payload_hash', 64);
            $table->foreignId('payment_id')->nullable()->constrained('order_payments')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('method', ['delivery', 'pickup']);
            $table->string('carrier', 120)->nullable();
            $table->string('reference', 120);
            $table->text('note');
            $table->foreignId('handed_over_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('handed_over_at');
        });
    }

    public function down(): void
    {
        if (DB::table('order_payments')->exists() || DB::table('orders')->where('status', 'completed')->orWhere('payment_status', 'partially_refunded')->exists()
            || DB::table('inventory_reservations')->where('status', 'consumed')->exists() || DB::table('stock_movements')->where('type', 'handover')->exists()) {
            throw new RuntimeException('Rollback refusé : historique de remise ou de remboursement à préserver.');
        }
        Schema::dropIfExists('order_fulfillments');
        Schema::dropIfExists('order_operations');
        Schema::dropIfExists('order_payments');
        Schema::table('order_histories', fn (Blueprint $table) => $table->dropColumn('context'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['verified_paid_minor', 'verified_refunded_minor', 'verified_net_minor']);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired'])->default('pending')->change();
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid')->change();
        });
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->enum('status', ['active', 'released', 'expired'])->default('active')->change());
        Schema::table('stock_movements', fn (Blueprint $table) => $table->enum('type', ['adjustment', 'reservation', 'release', 'expiry'])->change());
    }
};
