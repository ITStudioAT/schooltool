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
        Schema::create('register_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreignId('schoolyear_id');
            $table->foreignId('register_id');
            $table->string('supervisor')->nullable();
            $table->date('date')->nullable();
            $table->time('from');
            $table->time('to');
            $table->unsignedInteger('max_registrations')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('register_dates');
    }
};
