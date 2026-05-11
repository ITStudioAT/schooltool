<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('timetable_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('timetable_imports', 'import_status')) {
                $table->string('import_status')->default('completed')->after('tt_last_date');
            }
            if (! Schema::hasColumn('timetable_imports', 'progress_current')) {
                $table->unsignedInteger('progress_current')->default(0)->after('import_status');
            }
            if (! Schema::hasColumn('timetable_imports', 'progress_total')) {
                $table->unsignedInteger('progress_total')->default(0)->after('progress_current');
            }
            if (! Schema::hasColumn('timetable_imports', 'import_message')) {
                $table->string('import_message')->nullable()->after('progress_total');
            }
            if (! Schema::hasColumn('timetable_imports', 'import_error')) {
                $table->text('import_error')->nullable()->after('import_message');
            }
            if (! Schema::hasColumn('timetable_imports', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('import_error');
            }
            if (! Schema::hasColumn('timetable_imports', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_imports', function (Blueprint $table) {
            $columns = collect([
                'import_status',
                'progress_current',
                'progress_total',
                'import_message',
                'import_error',
                'started_at',
                'finished_at',
            ])->filter(fn (string $column): bool => Schema::hasColumn('timetable_imports', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
