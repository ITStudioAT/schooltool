<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_ingredient_icons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'title'], 'restaurant_ingredient_icons_school_title_unique');
            $table->index(['school_id', 'sort_order'], 'restaurant_icons_school_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ingredient_icons');
    }
};
