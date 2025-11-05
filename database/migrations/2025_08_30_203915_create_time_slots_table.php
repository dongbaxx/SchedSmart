    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration {
        public function up(): void {
            Schema::create('time_slots', function (Blueprint $table) {
                $table->id();
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedSmallInteger('minutes')->virtualAs('timestampdiff(MINUTE, start_time, end_time)'); // MariaDB 10.4 supports generated columns
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // No duplicate ranges
                $table->unique(['start_time', 'end_time']);
                // Optional check: end > start (skip if your MariaDB disallows CHECK)
                // $table->check('end_time > start_time');
            });
        }
        public function down(): void {
            Schema::dropIfExists('time_slots');
        }
    };
