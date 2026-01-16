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

        if (! Schema::hasColumn('register_date_bookings', 'note')) {
            Schema::table('register_date_bookings', function (Blueprint $table) {
                $table->string('note')->nullable();
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

        if (Schema::hasColumn('register_date_bookings', 'note')) {
            Schema::table('register_date_bookings', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
