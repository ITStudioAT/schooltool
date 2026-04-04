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
        if (
            ! Schema::hasTable('restaurant_menu_plans')
            || Schema::hasColumn('restaurant_menu_plans', 'use_individual_schedule_values')
        ) {
            return;
        }

        Schema::table('restaurant_menu_plans', function (Blueprint $table) {
            $table->boolean('use_individual_schedule_values')
                ->default(false)
                ->after('order_end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            ! Schema::hasTable('restaurant_menu_plans')
            || ! Schema::hasColumn('restaurant_menu_plans', 'use_individual_schedule_values')
        ) {
            return;
        }

        Schema::table('restaurant_menu_plans', function (Blueprint $table) {
            $table->dropColumn('use_individual_schedule_values');
        });
    }
};
