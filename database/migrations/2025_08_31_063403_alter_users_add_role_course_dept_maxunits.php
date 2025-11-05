<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Role (string para simple)
            $table->string('role')->nullable()->after('password');

            // Foreign keys (nullable para di mag-break ang existing rows)
            $table->foreignId('course_id')->nullable()->after('role')
                  ->constrained()->nullOnDelete();

            $table->foreignId('department_id')->nullable()->after('course_id')
                  ->constrained()->nullOnDelete();

            // Optional: maximum units (matches your notes)
            $table->unsignedTinyInteger('max_units')->default(0)->after('department_id');

            // Optional indexes
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // drop foreign keys then columns
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('department_id');

            $table->dropColumn(['role', 'max_units']);
            $table->dropIndex(['role']);
        });
    }
};
