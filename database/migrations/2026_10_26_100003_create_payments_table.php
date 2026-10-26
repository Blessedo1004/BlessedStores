<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('paystack_reference')->unique();
            $table->decimal('expected_amount', 12, 2);
            $table->char('currency', 3)->default('NGN');
            $table->enum('status', [
                'pending',
                'success',
                'failed',
                'abandoned',
                'refund_pending',
                'refunded',
            ])->default('pending')->index();
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};