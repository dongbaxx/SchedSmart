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
        Schema::create('administrative_loads', function (Blueprint $table) {
        $table->id();
        $table->string('load_desc');
        $table->tinyInteger('units');
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrative_loads');
    }
};
