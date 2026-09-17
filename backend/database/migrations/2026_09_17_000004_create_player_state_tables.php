<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('diamonds')->default(0);
            $table->unsignedBigInteger('soft_currency')->default(0);
            $table->timestamps();
        });

        Schema::create('tower_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_equipped')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'code']);
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 80);
            $table->string('item_key', 120);
            $table->unsignedInteger('quantity')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'item_type', 'item_key']);
        });

        Schema::create('boosts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'code']);
        });

        Schema::create('loadouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('slots')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loadouts');
        Schema::dropIfExists('boosts');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('tower_cards');
        Schema::dropIfExists('wallets');
    }
};
