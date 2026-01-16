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
        if (! Schema::hasTable('register_date_bookings')) {
            Schema::create('register_date_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id');
                $table->foreignId('schoolyear_id');
                $table->foreignId('register_id');
                $table->foreignId('register_date_id');
                $table->string('student_last_name')->nullable();
                $table->string('student_first_name')->nullable();
                $table->date('student_birthdate')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('register_date_bookings');
    }
};
