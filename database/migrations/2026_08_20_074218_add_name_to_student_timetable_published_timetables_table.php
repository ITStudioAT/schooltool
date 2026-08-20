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
        Schema::table('student_timetable_published_timetables', function (Blueprint $table) {
            $table->string('name', 5)->nullable()->after('student_label');
            $table->unique(
                ['school_id', 'name'],
                'student_tt_published_school_name_unique',
            );
        });
    }
};
