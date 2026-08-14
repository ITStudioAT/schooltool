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
        Schema::table('student_timetable_v3_timetables', function (Blueprint $table) {
            $table->index('school_id', 'student_tt_v3_timetables_school_index');
        });

        Schema::table('student_timetable_v3_timetables', function (Blueprint $table) {
            $table->dropUnique('student_tt_v3_timetables_scope_unique');
            $table->char('session_id_hash', 64)->nullable()->after('user_id');
            $table->timestamp('expires_at')->nullable()->after('generated_at');

            $table->unique(
                ['school_id', 'schoolyear_id', 'user_id', 'session_id_hash', 'context_key'],
                'student_tt_v3_timetables_session_scope_unique',
            );
            $table->index(
                ['school_id', 'user_id', 'session_id_hash'],
                'student_tt_v3_timetables_session_index',
            );
            $table->index('expires_at', 'student_tt_v3_timetables_expiry_index');
        });
    }
};
