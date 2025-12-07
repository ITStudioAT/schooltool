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
        Schema::create('school_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->boolean('tutoring_student_must_be_confirmed')->default(false);
            $table->string('tutoring_confirmer_email')->nullable();
            $table->unsignedInteger('tutoring_max_offers_per_student')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_tools');
    }
};
