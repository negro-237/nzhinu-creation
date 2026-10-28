<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'cultural_collections'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft')->index();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collection_product', function (Blueprint $table) {
            $table->foreignId('cultural_collection_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->primary(['cultural_collection_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('label', 100)->default('Standard');
            $table->string('size', 50)->nullable();
            $table->string('color', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('market_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->boolean('is_available')->default(false);
            $table->timestamps();
            $table->unique(['market_id', 'product_id']);
        });

        Schema::create('market_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->enum('currency', ['CAD', 'XAF']);
            $table->timestamps();
            $table->unique(['market_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        foreach (['market_prices', 'market_products', 'product_variants', 'collection_product', 'products', 'cultural_collections', 'categories'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
