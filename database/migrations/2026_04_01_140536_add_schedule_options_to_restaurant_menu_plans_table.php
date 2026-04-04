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
            if (! Schema::hasColumn('restaurant_menu_plans', 'visibility_start_mode')) {
                $table->string('visibility_start_mode')->nullable()->after('order_end_at');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'visibility_start_week_offset')) {
                $table->unsignedTinyInteger('visibility_start_week_offset')->nullable()->after('visibility_start_mode');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'visibility_start_day_of_week')) {
                $table->unsignedTinyInteger('visibility_start_day_of_week')->nullable()->after('visibility_start_week_offset');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'visibility_start_time')) {
                $table->time('visibility_start_time')->nullable()->after('visibility_start_day_of_week');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_start_mode')) {
                $table->string('order_start_mode')->nullable()->after('visibility_start_time');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_start_week_offset')) {
                $table->unsignedTinyInteger('order_start_week_offset')->nullable()->after('order_start_mode');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_start_day_of_week')) {
                $table->unsignedTinyInteger('order_start_day_of_week')->nullable()->after('order_start_week_offset');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_start_time')) {
                $table->time('order_start_time')->nullable()->after('order_start_day_of_week');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_end_week_offset')) {
                $table->unsignedTinyInteger('order_end_week_offset')->nullable()->after('order_start_time');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_end_day_of_week')) {
                $table->unsignedTinyInteger('order_end_day_of_week')->nullable()->after('order_end_week_offset');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'order_end_time')) {
                $table->time('order_end_time')->nullable()->after('order_end_day_of_week');
            }

            if (! Schema::hasColumn('restaurant_menu_plans', 'visibility_end_mode')) {
                $table->string('visibility_end_mode')->nullable()->after('order_end_time');
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
            'visibility_start_mode',
            'visibility_start_week_offset',
            'visibility_start_day_of_week',
            'visibility_start_time',
            'order_start_mode',
            'order_start_week_offset',
            'order_start_day_of_week',
            'order_start_time',
            'order_end_week_offset',
            'order_end_day_of_week',
            'order_end_time',
            'visibility_end_mode',
        ])->filter(fn (string $column): bool => Schema::hasColumn('restaurant_menu_plans', $column))->all();

        if ($columnsToDrop !== []) {
            Schema::table('restaurant_menu_plans', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
