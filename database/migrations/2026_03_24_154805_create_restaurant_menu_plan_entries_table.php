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
        Schema::create('restaurant_menu_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_menu_plan_id')->constrained()->cascadeOnDelete();
            $table->date('plan_date');
            $table->foreignId('restaurant_menu_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_override', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['restaurant_menu_plan_id', 'plan_date'], 'rmp_entries_plan_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_plan_entries');
    }
};
