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
            $table->string('finish_until_time', 5)->nullable()->after('finish_until_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_works', function (Blueprint $table) {
            $table->dropColumn('finish_until_time');
        });
    }
};
