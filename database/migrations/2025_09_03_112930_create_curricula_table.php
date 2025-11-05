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
        Schema::create('curricula', function (Blueprint $table) {
        $table->id();
        $table->string('course_code');
        $table->string('descriptive_title');
        $table->tinyInteger('units')->nullable();
        $table->tinyInteger('lec')->nullable();
        $table->tinyInteger('lab')->nullable();
        $table->tinyInteger('cmo')->nullable();
        $table->tinyInteger('hei')->nullable();
        $table->string('pre_requisite')->default('None');
        $table->foreignId('course_id')->constrained()->cascadeOnDelete();
        $table->string('year_level');
        $table->foreignId('specialization_id')->nullable()->constrained()->nullOnDelete();
        $table->string('efectivity_year')->nullable(); // typo preserved to match DB
        $table->string('semester')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
