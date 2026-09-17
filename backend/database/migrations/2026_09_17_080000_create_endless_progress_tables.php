<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endless_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_run_id');
            $table->unsignedInteger('highest_wave')->default(0);
            $table->unsignedInteger('defeated_enemies')->default(0);
            $table->unsignedInteger('lives_remaining')->default(20);
            $table->unsignedBigInteger('soft_currency_earned')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'client_run_id']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('wave_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('endless_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('wave');
            $table->unsignedInteger('defeated_enemies')->default(0);
            $table->unsignedInteger('lives_remaining')->default(20);
            $table->unsignedBigInteger('reward')->default(0);
            $table->timestamps();
            $table->unique(['endless_run_id', 'wave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wave_checkpoints');
        Schema::dropIfExists('endless_runs');
    }
};
