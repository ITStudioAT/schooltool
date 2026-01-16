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
        if (! Schema::hasTable('register_date_bookings')) {
            return;
        }

        if (! Schema::hasColumn('register_date_bookings', 'user_id')) {
            Schema::table('register_date_bookings', function (Blueprint $table) {
                $table->foreignId('user_id')->after('register_date_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('register_date_bookings')) {
            return;
        }

        if (Schema::hasColumn('register_date_bookings', 'user_id')) {
            Schema::table('register_date_bookings', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
