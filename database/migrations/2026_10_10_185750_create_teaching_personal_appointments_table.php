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
        Schema::create('teaching_personal_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('title', 120)->nullable();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->json('school_hours')->nullable();
            $table->json('time_segments')->nullable();
            $table->date('repeat_until')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'school_id', 'schoolyear_id'], 'personal_appointment_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_personal_appointments');
    }
};
