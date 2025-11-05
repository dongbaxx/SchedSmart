<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('sections') && !Schema::hasColumn('sections','status')) {
            Schema::table('sections', function (Blueprint $t) {
                $t->string('status',20)->default('active')->after('year_level');
            });
        }

        if (Schema::hasTable('section_meetings') && !Schema::hasColumn('section_meetings','status')) {
            Schema::table('section_meetings', function (Blueprint $t) {
                $t->string('status',20)->default('active')->after('end_time');
            });
        }
    }

    public function down(): void {
        if (Schema::hasTable('sections') && Schema::hasColumn('sections','status')) {
            Schema::table('sections', fn(Blueprint $t) => $t->dropColumn('status'));
        }

        if (Schema::hasTable('section_meetings') && Schema::hasColumn('section_meetings','status')) {
            Schema::table('section_meetings', fn(Blueprint $t) => $t->dropColumn('status'));
        }
    }
};
