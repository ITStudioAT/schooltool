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

        if (! Schema::hasIndex('register_dates', 'register_dates_unique_slot')) {
            Schema::table('register_dates', function (Blueprint $table) {
                $table->unique(
                    ['school_id', 'schoolyear_id', 'register_id', 'supervisor', 'date', 'from', 'to'],
                    'register_dates_unique_slot'
                );
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

        if (Schema::hasIndex('register_dates', 'register_dates_unique_slot')) {
            Schema::table('register_dates', function (Blueprint $table) {
                $table->dropUnique('register_dates_unique_slot');
            });
        }
    }
};
