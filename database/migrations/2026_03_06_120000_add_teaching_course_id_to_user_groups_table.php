<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->foreignId('teaching_course_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('teaching_courses')
                ->cascadeOnDelete();

            $table->unique('teaching_course_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_groups', function (Blueprint $table) {
            $table->dropUnique(['teaching_course_id']);
            $table->dropConstrainedForeignId('teaching_course_id');
        });
    }
};
