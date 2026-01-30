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
        Schema::create('teaching_course_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_course_id')->nullable()->index();
            $table->date('date')->nullable();
            $table->json('hours')->nullable();
            $table->text('content')->nullable();
            $table->json('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_course_dates');
    }
};
