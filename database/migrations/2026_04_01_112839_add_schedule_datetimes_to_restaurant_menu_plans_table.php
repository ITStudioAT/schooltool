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
        if (! Schema::hasTable('restaurant_menu_plans')) {
            return;
        }

        Schema::table('restaurant_menu_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_menu_plans', 'visible_start_at')) {
                $table->dateTime('visible_start_at')->nullable()->after('is_available');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'visible_end_at')) {
                $table->dateTime('visible_end_at')->nullable()->after('visible_start_at');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_start_at')) {
                $table->dateTime('order_start_at')->nullable()->after('visible_end_at');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_end_at')) {
                $table->dateTime('order_end_at')->nullable()->after('order_start_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('restaurant_menu_plans')) {
            return;
        }

        $columnsToDrop = collect([
            'visible_start_at',
            'visible_end_at',
            'order_start_at',
            'order_end_at',
        ])->filter(fn (string $column): bool => Schema::hasColumn('restaurant_menu_plans', $column))->all();

        if ($columnsToDrop !== []) {
            Schema::table('restaurant_menu_plans', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
