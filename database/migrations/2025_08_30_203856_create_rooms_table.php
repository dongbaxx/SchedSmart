<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('code');           // e.g., LAB-201, Rm 101
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent duplicate codes inside same building
            $table->unique(['building_id', 'code']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('rooms');
    }
};
