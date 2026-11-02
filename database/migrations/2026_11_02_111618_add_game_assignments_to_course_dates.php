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
        Schema::table('teaching_course_dates', function (Blueprint $table): void {
            $table->json('games')->nullable();
        });
        Schema::table('number_system_game_sessions', function (Blueprint $table): void {
            $table->foreignId('teaching_course_date_id')->nullable()->constrained('teaching_course_dates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('number_system_game_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('teaching_course_date_id');
        });
        Schema::table('teaching_course_dates', function (Blueprint $table): void {
            $table->dropColumn('games');
        });
    }
};
