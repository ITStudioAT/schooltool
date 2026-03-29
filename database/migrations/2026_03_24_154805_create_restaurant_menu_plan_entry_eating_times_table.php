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
        Schema::create('restaurant_menu_plan_entry_eating_times', function (Blueprint $table) {
            $table->unsignedBigInteger('restaurant_menu_plan_entry_id');
            $table->unsignedBigInteger('restaurant_eating_time_id');
            $table->primary(['restaurant_menu_plan_entry_id', 'restaurant_eating_time_id'], 'rmpeet_primary');
            $table->foreign('restaurant_menu_plan_entry_id', 'rmpeet_entry_fk')
                ->references('id')->on('restaurant_menu_plan_entries')->cascadeOnDelete();
            $table->foreign('restaurant_eating_time_id', 'rmpeet_time_fk')
                ->references('id')->on('restaurant_eating_times')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_plan_entry_eating_times');
    }
};
