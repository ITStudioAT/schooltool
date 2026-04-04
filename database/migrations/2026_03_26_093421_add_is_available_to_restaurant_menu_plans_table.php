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
        if (! Schema::hasTable('restaurant_menu_plans') || Schema::hasColumn('restaurant_menu_plans', 'is_available')) {
            return;
        }

        Schema::table('restaurant_menu_plans', function (Blueprint $table) {
            $table->boolean('is_available')->default(false)->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('restaurant_menu_plans') || ! Schema::hasColumn('restaurant_menu_plans', 'is_available')) {
            return;
        }

        Schema::table('restaurant_menu_plans', function (Blueprint $table) {
            $table->dropColumn('is_available');
        });
    }
};
