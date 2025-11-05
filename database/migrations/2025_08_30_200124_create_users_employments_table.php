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
        Schema::create('users_employments', function (Blueprint $table) {
        $table->id();
        $table->string('employment_classification');
        $table->string('employment_status');
        $table->tinyInteger('regular_load')->nullable();
        $table->tinyInteger('extra_load')->nullable();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_employments');
    }
};
