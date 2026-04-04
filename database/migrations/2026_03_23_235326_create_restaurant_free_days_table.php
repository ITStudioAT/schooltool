<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_free_days')) {
            return;
        }

        Schema::create('restaurant_free_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('free_date');
            $table->timestamps();

            $table->unique(['school_id', 'free_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_free_days');
    }
};
