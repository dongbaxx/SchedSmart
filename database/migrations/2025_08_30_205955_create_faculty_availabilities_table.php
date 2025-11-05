<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faculty_availabilities', function (Blueprint $table) {
            $table->id();

            // Who
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();


            $table->enum('day', ['MON','TUE','WED','THU','FRI','SAT']);
            $table->foreignId('time_slot_id')->constrained('time_slots')->restrictOnDelete();

            $table->boolean('is_available')->default(true);
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();
            $table->unique(['user_id','day','time_slot_id'], 'uniq_faculty_day_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_availabilities');
    }
};
