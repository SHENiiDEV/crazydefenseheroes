<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('product_type', 40);
            $table->string('grant_key', 120)->nullable();
            $table->unsignedInteger('grant_quantity')->default(1);
            $table->unsignedInteger('price_minor');
            $table->char('currency', 3)->default('EUR');
            $table->json('metadata')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('provider', 40)->nullable();
            $table->string('provider_payment_id', 160)->nullable()->unique();
            $table->string('idempotency_key', 160);
            $table->unsignedInteger('total_minor');
            $table->char('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_code', 80);
            $table->string('product_name', 160);
            $table->string('product_type', 40);
            $table->string('grant_key', 120)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('grant_quantity');
            $table->unsignedInteger('unit_price_minor');
            $table->char('currency', 3);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_id', 160);
            $table->string('event_type', 80);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 180)->unique();
            $table->string('type', 40);
            $table->bigInteger('diamonds_delta')->default(0);
            $table->bigInteger('soft_currency_delta')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
    }
};
