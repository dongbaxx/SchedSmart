<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('course_offerings', function (Blueprint $t) {
            if (!Schema::hasColumn('course_offerings','status')) {
                $t->enum('status', ['draft','pending','locked','archived'])
                  ->default('draft')
                  ->after('effectivity_year');
            } else {
                // kung existing na ang status column pero walay "archived"
                // safe way: convert to string (mas flexible long-term)
                $t->string('status', 20)->default('draft')->change();
            }

            if (!Schema::hasColumn('course_offerings','approved_by')) {
                $t->unsignedBigInteger('approved_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('course_offerings','approved_at')) {
                $t->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });
    }

    public function down(): void {
        Schema::table('course_offerings', function (Blueprint $t) {
            if (Schema::hasColumn('course_offerings','approved_at')) {
                $t->dropColumn('approved_at');
            }
            if (Schema::hasColumn('course_offerings','approved_by')) {
                $t->dropColumn('approved_by');
            }
            if (Schema::hasColumn('course_offerings','status')) {
                $t->dropColumn('status');
            }
        });
    }
};
