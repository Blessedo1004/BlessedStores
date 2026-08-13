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
        Schema::create('application_socials', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('store_application_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('user_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_socials');
    }
};
