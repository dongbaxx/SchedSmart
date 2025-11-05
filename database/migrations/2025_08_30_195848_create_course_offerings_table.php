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
    Schema::create('course_offerings', function (Blueprint $table) {
        $table->id();
        $table->string('year_level');
        $table->foreignId('academic_id')->constrained('academic_years')->cascadeOnDelete();
        $table->foreignId('course_id')->constrained()->cascadeOnDelete();
        $table->foreignId('section_id')->constrained()->cascadeOnDelete();
        $table->string('effectivity_year')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
