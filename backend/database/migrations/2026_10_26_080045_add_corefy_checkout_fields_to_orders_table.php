<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('checkout_url')->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->boolean('payment_test_mode')->nullable();
            $table->timestamp('digital_content_consent_at')->nullable();
            $table->string('purchase_terms_version', 40)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['checkout_url', 'provider_status', 'payment_test_mode', 'digital_content_consent_at', 'purchase_terms_version']);
        });
    }
};
