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
            $table->date('finish_until_date')->nullable()->after('date_for_all_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_works', function (Blueprint $table) {
            $table->dropColumn('finish_until_date');
        });
    }
};
