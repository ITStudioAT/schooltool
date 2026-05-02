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
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('school_tools', 'students_timetables_visible_admin')) {
                $table->boolean('students_timetables_visible_admin')->default(false)->after('aba_user_comming_soon');
            }

            if (! Schema::hasColumn('school_tools', 'students_timetables_visible_user')) {
                $table->boolean('students_timetables_visible_user')->default(false)->after('students_timetables_visible_admin');
            }

            if (! Schema::hasColumn('school_tools', 'students_timetables_user_test_mode')) {
                $table->boolean('students_timetables_user_test_mode')->default(false)->after('students_timetables_visible_user');
            }

            if (! Schema::hasColumn('school_tools', 'students_timetables_user_comming_soon')) {
                $table->boolean('students_timetables_user_comming_soon')->default(false)->after('students_timetables_user_test_mode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            foreach ([
                'students_timetables_user_comming_soon',
                'students_timetables_user_test_mode',
                'students_timetables_visible_user',
                'students_timetables_visible_admin',
            ] as $column) {
                if (Schema::hasColumn('school_tools', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
