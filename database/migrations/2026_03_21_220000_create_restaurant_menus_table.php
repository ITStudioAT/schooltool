<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_menus')) {
            return;
        }

        Schema::create('restaurant_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('price', 8, 2);
            $table->timestamps();

            $table->index(['school_id', 'title'], 'restaurant_menus_school_title_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menus');
    }
};
