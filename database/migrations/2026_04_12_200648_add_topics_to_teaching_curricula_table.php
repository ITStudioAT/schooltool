<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('teaching_curricula', 'topics')) {
            return;
        }

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->json('topics')->nullable()->after('free_weeks');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('teaching_curricula', 'topics')) {
            return;
        }

        Schema::table('teaching_curricula', function (Blueprint $table) {
            $table->dropColumn('topics');
        });
    }
};
