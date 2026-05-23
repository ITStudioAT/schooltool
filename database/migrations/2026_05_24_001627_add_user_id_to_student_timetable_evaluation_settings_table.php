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
        Schema::table('student_timetable_evaluation_settings', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['schoolyear_id']);
            $table->dropUnique('student_tt_evaluation_settings_scope_unique');
        });

        Schema::table('student_timetable_evaluation_settings', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('schoolyear_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('schoolyear_id')->references('id')->on('schoolyears')->cascadeOnDelete();

            $table->unique(
                ['school_id', 'schoolyear_id', 'user_id'],
                'student_tt_evaluation_settings_user_scope_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_timetable_evaluation_settings', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['schoolyear_id']);
            $table->dropUnique('student_tt_evaluation_settings_user_scope_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('student_timetable_evaluation_settings', function (Blueprint $table) {
            $table->unique(
                ['school_id', 'schoolyear_id'],
                'student_tt_evaluation_settings_scope_unique',
            );

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('schoolyear_id')->references('id')->on('schoolyears')->cascadeOnDelete();
        });
    }
};
