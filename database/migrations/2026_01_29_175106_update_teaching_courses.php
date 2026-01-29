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
        Schema::table('teaching_courses', function (Blueprint $table) {
            $table->json('students')->nullable()->after('classes');
            $table->json('students_deleted')->nullable()->after('students');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_courses', function (Blueprint $table) {
            //
        });
    }
};
