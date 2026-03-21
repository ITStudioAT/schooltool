<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_foods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_category_id')->nullable()->constrained('restaurant_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('allergens')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->string('food_image_path')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'title'], 'restaurant_foods_school_title_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_foods');
    }
};
