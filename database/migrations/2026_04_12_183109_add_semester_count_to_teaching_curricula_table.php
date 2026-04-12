<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('teaching_curricula', 'semester_count')) {
            return;
        }

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester_count')->default(2)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->dropColumn('semester_count');
        });
    }
};
