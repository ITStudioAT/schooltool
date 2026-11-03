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
        Schema::table('teaching_personal_appointments', function (Blueprint $table) {
            $table->json('title_exceptions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_personal_appointments', function (Blueprint $table) {
            $table->dropColumn('title_exceptions');
        });
    }
};
