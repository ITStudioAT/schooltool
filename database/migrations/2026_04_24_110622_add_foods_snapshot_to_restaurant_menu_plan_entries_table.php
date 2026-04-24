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
        if (! Schema::hasTable('restaurant_menu_plan_entries')) {
            return;
        }

        Schema::table('restaurant_menu_plan_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_menu_plan_entries', 'foods_snapshot')) {
                $table->json('foods_snapshot')->nullable()->after('comments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('restaurant_menu_plan_entries')) {
            return;
        }

        Schema::table('restaurant_menu_plan_entries', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_menu_plan_entries', 'foods_snapshot')) {
                $table->dropColumn('foods_snapshot');
            }
        });
    }
};
