<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 160);
            $table->text('body');
            $table->string('meta_description', 255);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_published', 'market_id']);
        });
        Schema::create('store_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('link_path', 255)->nullable();
            $table->string('link_label', 80)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_published', 'market_id', 'sort_order']);
        });
        Schema::create('order_notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_history_id')->unique()->constrained('order_histories')->restrictOnDelete();
            $table->string('kind', 40);
            $table->timestamp('expanded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->unique();
            $table->foreignId('order_notification_event_id')->constrained('order_notification_events', indexName: 'order_delivery_event_fk')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('recipient_key', 50);
            $table->enum('channel', ['mail', 'database']);
            $table->enum('status', ['pending', 'sent', 'skipped', 'failed'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 160)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['order_notification_event_id', 'recipient_key', 'channel'], 'order_delivery_unique');
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notification_deliveries');
        Schema::dropIfExists('order_notification_events');
        Schema::dropIfExists('store_banners');
        Schema::dropIfExists('cms_pages');
    }
};
