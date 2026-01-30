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
            $table->text('description')->nullable()->after('title');
            $table->json('reminder')->nullable()->after('students_deleted');
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
