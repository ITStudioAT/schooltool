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
        Schema::table('student_timetable_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('student_timetable_entries', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('student_group');
            }
        });
    }
};
