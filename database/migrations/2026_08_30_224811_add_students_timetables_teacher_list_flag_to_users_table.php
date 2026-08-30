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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('students_timetables_teacher_listed')
                ->default(false)
                ->after('is_active');
            $table->index(
                ['school_id', 'students_timetables_teacher_listed'],
                'users_school_tt_teacher_listed_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_school_tt_teacher_listed_index');
            $table->dropColumn('students_timetables_teacher_listed');
        });
    }
};
