<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_foods') && ! Schema::hasColumn('restaurant_foods', 'legacy_food_id')) {
            Schema::table('restaurant_foods', function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_food_id')->nullable()->after('school_id');
                $table->unique(['school_id', 'legacy_food_id'], 'restaurant_foods_school_legacy_food_unique');
            });
        }

        if (Schema::hasTable('restaurant_menus') && ! Schema::hasColumn('restaurant_menus', 'legacy_menu_id')) {
            Schema::table('restaurant_menus', function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_menu_id')->nullable()->after('school_id');
                $table->unique(['school_id', 'legacy_menu_id'], 'restaurant_menus_school_legacy_menu_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restaurant_foods') && Schema::hasColumn('restaurant_foods', 'legacy_food_id')) {
            Schema::table('restaurant_foods', function (Blueprint $table) {
                $table->dropUnique('restaurant_foods_school_legacy_food_unique');
                $table->dropColumn('legacy_food_id');
            });
        }

        if (Schema::hasTable('restaurant_menus') && Schema::hasColumn('restaurant_menus', 'legacy_menu_id')) {
            Schema::table('restaurant_menus', function (Blueprint $table) {
                $table->dropUnique('restaurant_menus_school_legacy_menu_unique');
                $table->dropColumn('legacy_menu_id');
            });
        }
    }
};
