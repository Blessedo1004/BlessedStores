<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('store_name');
            $table->decimal('product_subtotal', 12, 2);
            $table->decimal('commission_rate', 5, 2)->default(3.00);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('vendor_share', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['order_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_orders');
    }
};