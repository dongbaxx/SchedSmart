<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // kung wala pa ang status column, add it
        if (!Schema::hasColumn('course_offerings','status')) {
            DB::statement("ALTER TABLE course_offerings
                ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER effectivity_year");
        } else {
            // kung enum siya karon, i-convert nato to varchar
            DB::statement("ALTER TABLE course_offerings
                MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        }

        // make sure walay null values
        DB::table('course_offerings')->whereNull('status')->update(['status' => 'draft']);
    }

    public function down(): void {
        // optional: balik sa enum kung gusto nimo
        DB::statement("ALTER TABLE course_offerings
            MODIFY COLUMN status ENUM('draft','pending','locked') NOT NULL DEFAULT 'draft'");
    }
};
