<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_food_restaurant_menu')) {
            return;
        }

        Schema::create('restaurant_food_restaurant_menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_menu_id')->constrained('restaurant_menus')->cascadeOnDelete();
            $table->foreignId('restaurant_food_id')->constrained('restaurant_foods')->cascadeOnDelete();
            $table->unsignedInteger('course_number');
            $table->timestamps();

            $table->unique(['restaurant_menu_id', 'restaurant_food_id'], 'restaurant_menu_food_unique');
            $table->unique(['restaurant_menu_id', 'course_number'], 'restaurant_menu_course_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_food_restaurant_menu');
    }
};
