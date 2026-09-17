<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('surname')->after('name');
            $table->string('phone', 32)->after('email');
            $table->date('date_of_birth')->after('phone');
        });

        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('street');
            $table->string('city');
            $table->string('country', 120);
            $table->string('postcode', 32);
            $table->timestamps();
        });

        Schema::create('policy_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('policy_type', 32);
            $table->string('policy_version', 32);
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['user_id', 'policy_type']);
            $table->index(['policy_type', 'policy_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acceptances');
        Schema::dropIfExists('addresses');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['surname', 'phone', 'date_of_birth']);
        });
    }
};
