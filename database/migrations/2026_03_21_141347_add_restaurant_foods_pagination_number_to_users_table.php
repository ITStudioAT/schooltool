<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'restaurant_foods_pagination_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('restaurant_foods_pagination_number')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'restaurant_foods_pagination_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('restaurant_foods_pagination_number');
            });
        }
    }
};
