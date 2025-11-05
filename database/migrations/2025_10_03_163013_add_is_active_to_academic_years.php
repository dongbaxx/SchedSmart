<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('academic_years', function (Blueprint $t) {
            if (!Schema::hasColumn('academic_years','is_active')) {
                $t->boolean('is_active')->default(0)->after('semester');
                $t->index('is_active');
            }
        });

        // Initialize: ensure exactly one active term
        if (Schema::hasColumn('academic_years','is_active')) {
            // if none is active, set the latest (highest id) as active
            $hasActive = DB::table('academic_years')->where('is_active',1)->exists();
            if (!$hasActive) {
                $latest = DB::table('academic_years')->orderByDesc('id')->first();
                if ($latest) {
                    DB::table('academic_years')->update(['is_active'=>0]);
                    DB::table('academic_years')->where('id',$latest->id)->update(['is_active'=>1]);
                }
            }
        }
    }

    public function down(): void {
        if (Schema::hasColumn('academic_years','is_active')) {
            Schema::table('academic_years', function (Blueprint $t) {
                $t->dropIndex(['is_active']);
                $t->dropColumn('is_active');
            });
        }
    }
};
