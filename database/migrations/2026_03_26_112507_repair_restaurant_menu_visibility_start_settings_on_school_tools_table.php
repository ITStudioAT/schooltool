<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        $addedColumn = false;

        Schema::table('school_tools', function (Blueprint $table) use (&$addedColumn) {
            if (! Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_mode')) {
                $table->string('restaurant_menu_visibility_start_mode')->default('when_available');
                $addedColumn = true;
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_week_offset')) {
                $table->unsignedTinyInteger('restaurant_menu_visibility_start_week_offset')->nullable();
                $addedColumn = true;
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_day_of_week')) {
                $table->unsignedTinyInteger('restaurant_menu_visibility_start_day_of_week')->nullable();
                $addedColumn = true;
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_time')) {
                $table->time('restaurant_menu_visibility_start_time')->nullable();
                $addedColumn = true;
            }
        });

        if (! $addedColumn) {
            return;
        }

        DB::table('school_tools')->update([
            'restaurant_menu_visibility_start_mode' => DB::raw("coalesce(restaurant_menu_order_start_mode, 'when_available')"),
            'restaurant_menu_visibility_start_week_offset' => DB::raw('restaurant_menu_order_start_week_offset'),
            'restaurant_menu_visibility_start_day_of_week' => DB::raw('restaurant_menu_order_start_day_of_week'),
            'restaurant_menu_visibility_start_time' => DB::raw('restaurant_menu_order_start_time'),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_time')) {
                $table->dropColumn('restaurant_menu_visibility_start_time');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_day_of_week')) {
                $table->dropColumn('restaurant_menu_visibility_start_day_of_week');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_week_offset')) {
                $table->dropColumn('restaurant_menu_visibility_start_week_offset');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_visibility_start_mode')) {
                $table->dropColumn('restaurant_menu_visibility_start_mode');
            }
        });
    }
};
