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
        Schema::create('tutoring_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->string('short_name')->nullable();
            $table->string('long_name')->nullable();
            $table->string('email_mentor')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutoring_subjects');
    }
};
