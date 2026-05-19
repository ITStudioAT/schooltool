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
        if (! Schema::hasColumn('student_timetable_recognition_imports', 'import_status')) {
            Schema::table('student_timetable_recognition_imports', function (Blueprint $table) {
                $table->string('import_status')->default('completed')->after('skipped_rows');
            });
        }

        if (! Schema::hasColumn('student_timetable_recognition_imports', 'import_message')) {
            Schema::table('student_timetable_recognition_imports', function (Blueprint $table) {
                $table->text('import_message')->nullable()->after('import_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_recognition_imports', function (Blueprint $table) {
            if (Schema::hasColumn('student_timetable_recognition_imports', 'import_message')) {
                $table->dropColumn('import_message');
            }

            if (Schema::hasColumn('student_timetable_recognition_imports', 'import_status')) {
                $table->dropColumn('import_status');
            }
        });
    }
};
