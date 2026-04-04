<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_food_restaurant_ingredient_icon')) {
            return;
        }

        Schema::create('restaurant_food_restaurant_ingredient_icon', function (Blueprint $table) {
            $table->foreignId('restaurant_food_id');
            $table->foreignId('restaurant_ingredient_icon_id');

            $table->foreign('restaurant_food_id', 'restaurant_food_icon_food_fk')
                ->references('id')
                ->on('restaurant_foods')
                ->cascadeOnDelete();
            $table->foreign('restaurant_ingredient_icon_id', 'restaurant_food_icon_icon_fk')
                ->references('id')
                ->on('restaurant_ingredient_icons')
                ->cascadeOnDelete();

            $table->unique(
                ['restaurant_food_id', 'restaurant_ingredient_icon_id'],
                'restaurant_food_icon_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_food_restaurant_ingredient_icon');
    }
};
