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
        if (! Schema::hasColumn('users', 'use_school_color_for_admin_ui')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('use_school_color_for_admin_ui')->default(true)->after('restaurant_foods_pagination_number');
            });
        }

        if (Schema::hasColumn('schools', 'use_school_color_for_admin_ui')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('use_school_color_for_admin_ui');
            });
        }
    }
};
