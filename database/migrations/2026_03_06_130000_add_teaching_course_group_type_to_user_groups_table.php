<?php

use App\Models\UserGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_groups', 'teaching_course_group_type')) {
            Schema::table('user_groups', function (Blueprint $table) {
                $table->string('teaching_course_group_type', 32)
                    ->nullable()
                    ->after('teaching_course_id');
            });
        }

        DB::table('user_groups')
            ->whereNotNull('teaching_course_id')
            ->update([
                'teaching_course_group_type' => UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS,
            ]);

        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropForeign(['teaching_course_id']);
        });

        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropUnique(['teaching_course_id']);
            $table->unique(['teaching_course_id', 'teaching_course_group_type'], 'user_groups_teaching_course_group_unique');
            $table->foreign('teaching_course_id')->references('id')->on('teaching_courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('user_groups')
            ->where('teaching_course_group_type', UserGroup::TEACHING_COURSE_GROUP_TYPE_PARENTS)
            ->delete();

        DB::table('user_groups')
            ->where('teaching_course_group_type', UserGroup::TEACHING_COURSE_GROUP_TYPE_STUDENTS)
            ->update([
                'teaching_course_group_type' => null,
            ]);

        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropForeign(['teaching_course_id']);
            $table->dropUnique('user_groups_teaching_course_group_unique');
            $table->unique('teaching_course_id');
            $table->foreign('teaching_course_id')->references('id')->on('teaching_courses')->cascadeOnDelete();
        });

        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropColumn('teaching_course_group_type');
        });
    }
};
