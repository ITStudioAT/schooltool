<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_course_students', function (Blueprint $table) {
            $table->dateTime('canceled_at')->nullable()->after('stars');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_course_students', function (Blueprint $table) {
            $table->dropColumn('canceled_at');
        });
    }
};
