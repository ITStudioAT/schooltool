<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->string('kind')->default('behaviour')->after('type');
            $table->date('due_date')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->dropColumn(['kind', 'due_date']);
        });
    }
};
