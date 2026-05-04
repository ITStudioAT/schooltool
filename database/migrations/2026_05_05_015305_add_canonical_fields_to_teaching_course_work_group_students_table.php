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
        Schema::table('teaching_course_work_group_students', function (Blueprint $table) {
            $table->dropUnique('tcwgs_work_user_unique');

            $table->unsignedInteger('group_index')->default(0)->after('user_id');
            $table->string('group_name')->nullable()->after('group_index');
            $table->date('group_date')->nullable()->after('group_name');
            $table->string('group_grade', 50)->nullable()->after('group_date');
            $table->text('group_comment')->nullable()->after('group_grade');
            $table->boolean('uses_individual_grades')->default(false)->after('group_comment');
            $table->string('student_grade', 50)->nullable()->after('uses_individual_grades');
            $table->decimal('student_points', 10, 2)->nullable()->after('student_grade');
            $table->text('student_comment')->nullable()->after('student_points');

            $table->unique(['teaching_course_work_id', 'group_index', 'user_id'], 'tcwgs_work_group_user_unique');
            $table->index(['teaching_course_work_id', 'group_index'], 'tcwgs_work_group_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_work_group_students', function (Blueprint $table) {
            $table->dropIndex('tcwgs_work_group_idx');
            $table->dropUnique('tcwgs_work_group_user_unique');

            $table->dropColumn([
                'group_index',
                'group_name',
                'group_date',
                'group_grade',
                'group_comment',
                'uses_individual_grades',
                'student_grade',
                'student_points',
                'student_comment',
            ]);

            $table->unique(['teaching_course_work_id', 'user_id'], 'tcwgs_work_user_unique');
        });
    }
};
