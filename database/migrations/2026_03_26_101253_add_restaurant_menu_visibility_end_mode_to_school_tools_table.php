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
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('school_tools', 'restaurant_menu_visibility_end_mode')) {
                $table->string('restaurant_menu_visibility_end_mode')->default('plan_end');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('school_tools')) {
            return;
        }

        Schema::table('school_tools', function (Blueprint $table) {
            if (Schema::hasColumn('school_tools', 'restaurant_menu_visibility_end_mode')) {
                $table->dropColumn('restaurant_menu_visibility_end_mode');
            }
        });
    }
};
