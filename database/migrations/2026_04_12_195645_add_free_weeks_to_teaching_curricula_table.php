<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('teaching_curricula', 'free_weeks')) {
            return;
        }

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->json('free_weeks')->nullable()->after('semester_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('teaching_curricula', 'free_weeks')) {
            return;
        }

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->dropColumn('free_weeks');
        });
    }
};
