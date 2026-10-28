<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->enum('code', ['CA', 'CM'])->unique();
            $table->string('name');
            $table->enum('currency', ['CAD', 'XAF']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->boolean('is_placeholder')->default(true);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['market_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('markets');
    }
};
