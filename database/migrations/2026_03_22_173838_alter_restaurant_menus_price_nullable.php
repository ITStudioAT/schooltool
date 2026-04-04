<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_menus') || ! Schema::hasColumn('restaurant_menus', 'price')) {
            return;
        }

        Schema::table('restaurant_menus', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('restaurant_menus') || ! Schema::hasColumn('restaurant_menus', 'price')) {
            return;
        }

        Schema::table('restaurant_menus', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->nullable(false)->change();
        });
    }
};
