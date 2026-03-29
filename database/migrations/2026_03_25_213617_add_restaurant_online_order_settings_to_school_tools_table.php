<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('school_tools', 'restaurant_menu_order_start_mode')) {
                $table->string('restaurant_menu_order_start_mode')->default('when_available');
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_order_start_week_offset')) {
                $table->unsignedTinyInteger('restaurant_menu_order_start_week_offset')->nullable();
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_order_start_day_of_week')) {
                $table->unsignedTinyInteger('restaurant_menu_order_start_day_of_week')->nullable();
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_order_start_time')) {
                $table->time('restaurant_menu_order_start_time')->nullable();
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_order_end_week_offset')) {
                $table->unsignedTinyInteger('restaurant_menu_order_end_week_offset')->nullable();
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_order_end_day_of_week')) {
                $table->unsignedTinyInteger('restaurant_menu_order_end_day_of_week')->nullable();
            }

            if (! Schema::hasColumn('school_tools', 'restaurant_menu_order_end_time')) {
                $table->time('restaurant_menu_order_end_time')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (Schema::hasColumn('school_tools', 'restaurant_menu_order_end_time')) {
                $table->dropColumn('restaurant_menu_order_end_time');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_order_end_day_of_week')) {
                $table->dropColumn('restaurant_menu_order_end_day_of_week');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_order_end_week_offset')) {
                $table->dropColumn('restaurant_menu_order_end_week_offset');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_order_start_time')) {
                $table->dropColumn('restaurant_menu_order_start_time');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_order_start_day_of_week')) {
                $table->dropColumn('restaurant_menu_order_start_day_of_week');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_order_start_week_offset')) {
                $table->dropColumn('restaurant_menu_order_start_week_offset');
            }

            if (Schema::hasColumn('school_tools', 'restaurant_menu_order_start_mode')) {
                $table->dropColumn('restaurant_menu_order_start_mode');
            }
        });
    }
};
