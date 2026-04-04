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
        Schema::table('restaurant_menu_plans', function (Blueprint $table) {
            $table->dateTime('visible_start_at')->nullable()->after('is_available');
            $table->dateTime('visible_end_at')->nullable()->after('visible_start_at');
            $table->dateTime('order_start_at')->nullable()->after('visible_end_at');
            $table->dateTime('order_end_at')->nullable()->after('order_start_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_menu_plans', function (Blueprint $table) {
            $table->dropColumn([
                'visible_start_at',
                'visible_end_at',
                'order_start_at',
                'order_end_at',
            ]);
        });
    }
};
