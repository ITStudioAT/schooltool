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
        if (! Schema::hasTable('register_dates')) {
            return;
        }

        if (! Schema::hasColumn('register_dates', 'is_locked')) {
            Schema::table('register_dates', function (Blueprint $table) {
                $table->boolean('is_locked')->after('max_registrations')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('register_dates')) {
            return;
        }

        if (Schema::hasColumn('register_dates', 'is_locked')) {
            Schema::table('register_dates', function (Blueprint $table) {
                $table->dropColumn('is_locked');
            });
        }
    }
};
