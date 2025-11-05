<?php

// database/migrations/2025_09_17_000200_create_section_meetings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('section_meetings', function (Blueprint $table) {
            $table->id();
            // Link to offering (section in a term). If wala kay FK, pwede section_id+academic_id.
            $table->foreignId('offering_id')->constrained('course_offerings')->cascadeOnDelete();

            // Subject (from curricula) and the faculty teaching this meeting
            $table->foreignId('curriculum_id')->constrained('curricula')->cascadeOnDelete();
            $table->foreignId('faculty_id')->constrained('users')->restrictOnDelete();

            // Room
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();

            // Day + time
            $table->enum('day', ['MON','TUE','WED','THU','FRI','SAT'])->index();
            $table->time('start_time');
            $table->time('end_time');

            // Optional: meeting type (LEC/LAB), notes
            $table->string('type')->nullable();      // 'LEC'/'LAB'
            $table->string('notes')->nullable();

            $table->timestamps();

            // Useful indexes to speed up conflict checks
            $table->index(['room_id','day','start_time','end_time']);
            $table->index(['faculty_id','day','start_time','end_time']);
            $table->index(['offering_id','day','start_time','end_time']);
        });
    }
    public function down(): void { Schema::dropIfExists('section_meetings'); }
};
