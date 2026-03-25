<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('restaurant_menu_plan_entries', function (Blueprint $table) {
            $table->renameColumn('price_override', 'price');
        });

        Schema::table('restaurant_menu_plan_entries', function (Blueprint $table) {
            $table->string('menu_title')->nullable()->after('restaurant_menu_id');
            $table->text('comments')->nullable()->after('price');
        });

        DB::table('restaurant_menu_plan_entries')
            ->orderBy('id')
            ->get(['id', 'restaurant_menu_id', 'price'])
            ->each(function (object $entry): void {
                $menu = DB::table('restaurant_menus')
                    ->where('id', $entry->restaurant_menu_id)
                    ->first(['title', 'price']);

                DB::table('restaurant_menu_plan_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'menu_title' => $menu?->title,
                        'price' => $entry->price ?? $menu?->price,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_menu_plan_entries', function (Blueprint $table) {
            $table->dropColumn(['menu_title', 'comments']);
        });

        Schema::table('restaurant_menu_plan_entries', function (Blueprint $table) {
            $table->renameColumn('price', 'price_override');
        });
    }
};
