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
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'tutoring_filter')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('tutoring_filter')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'tutoring_filter')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tutoring_filter');
            });
        }
    }
};
