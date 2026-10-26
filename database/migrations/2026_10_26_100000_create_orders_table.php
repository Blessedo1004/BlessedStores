<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->index();
            $table->string('customer_phone', 32);
            $table->text('delivery_address');
            $table->string('delivery_city');
            $table->string('delivery_state');
            $table->string('delivery_postal_code')->nullable();
            $table->decimal('product_subtotal', 12, 2);
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_payable', 12, 2);
            $table->char('currency', 3)->default('NGN');
            $table->enum('status', ['pending', 'processed', 'delivered'])->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};