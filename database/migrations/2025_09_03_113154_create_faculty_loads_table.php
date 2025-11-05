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
        Schema::create('faculty_loads', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('curriculum_id')->constrained('curricula')->cascadeOnDelete();
        $table->string('contact_hours'); // keep varchar since dump uses varchar
        $table->foreignId('administrative_id')->nullable()->constrained('administrative_loads')->cascadeOnDelete();
        $table->string('section');
        $table->foreignId('academic_id')->constrained('academic_years')->cascadeOnDelete();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_loads');
    }
};
