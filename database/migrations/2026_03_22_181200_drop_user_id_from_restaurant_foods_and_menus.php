<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('restaurant_foods', 'user_id')) {
            Schema::table('restaurant_foods', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasColumn('restaurant_menus', 'user_id')) {
            Schema::table('restaurant_menus', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('restaurant_foods', 'user_id')) {
            Schema::table('restaurant_foods', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('restaurant_menus', 'user_id')) {
            Schema::table('restaurant_menus', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
            });
        }
    }
};
