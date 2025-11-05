<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('specializations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // link to courses instead of programs
            $table->foreignId('course_id')->nullable()
                  ->constrained('courses')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_id','name']); // one name per course
        });
    }

    public function down(): void {
        Schema::dropIfExists('specializations');
    }
};
