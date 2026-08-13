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
        Schema::create('store_applications', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('owner_name');
            $table->string('store_name')->index();
            $table->string('email')->unique();
            $table->string('phone_number');
            $table->text('address');
            $table->string('logo');
            $table->string('slug')->unique();
            $table->text('description');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_applications');
    }
};
