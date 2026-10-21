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
        Schema::table('stores', function (Blueprint $table) {
            $table->renameColumn('status', 'store_status');
            $table->string('paystack_subaccount_code')->nullable()->unique();
            $table->enum('payment_setup_status', ['not_started', 'pending', 'processing', 'ready', 'failed', 'requires_review' ])
            ->default('not_started');
            $table->timestamp('payment_setup_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique(['paystack_subaccount_code']);
            $table->dropColumn(['paystack_subaccount_code', 'payment_setup_status', 'payment_setup_completed_at']);
            $table->renameColumn('store_status', 'status');
        });
    }
};
