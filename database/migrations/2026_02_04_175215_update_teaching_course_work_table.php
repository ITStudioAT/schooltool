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
        Schema::table('teaching_course_works', function (Blueprint $table) {
            $table->boolean('is_group_work')->default(false)->after('description');
            $table->integer('group_size')->nullable()->after('is_group_work');
            $table->boolean('is_random_groups')->default(false)->after('group_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_works', function (Blueprint $table) {
            //
        });
    }
};
