<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'pending', 'processed', 'delivered'])
                ->default('unpaid')
                ->change();
        });
    }

    public function down(): void
    {
        if (DB::table('orders')->where('status', 'unpaid')->exists()) {
            throw new RuntimeException('Cannot remove the unpaid order status while unpaid orders exist.');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processed', 'delivered'])
                ->default('pending')
                ->change();
        });
    }
};