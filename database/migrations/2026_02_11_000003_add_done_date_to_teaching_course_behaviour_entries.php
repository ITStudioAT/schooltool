<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->date('done_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->dropColumn('done_date');
        });
    }
};
