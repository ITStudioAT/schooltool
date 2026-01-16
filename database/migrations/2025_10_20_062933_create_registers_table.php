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
        if (! Schema::hasTable('registers')) {
            Schema::create('registers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id');
                $table->foreignId('schoolyear_id');
                $table->string('name')->nullable();
                $table->text('description_on_website')->nullable();
                $table->integer('max_registrations')->default(0);
                $table->boolean('show_phone')->default(1);
                $table->boolean('must_phone')->default(1);
                $table->boolean('show_student_last_name')->default(1);
                $table->boolean('must_student_last_name')->default(1);
                $table->boolean('show_student_first_name')->default(1);
                $table->boolean('must_student_first_name')->default(1);
                $table->boolean('show_student_birthdate')->default(1);
                $table->boolean('must_student_birthdate')->default(1);
                $table->boolean('show_booked')->default(1);
                $table->boolean('show_end_time')->default(1);
                $table->boolean('show_supervisor')->default(0);
                $table->boolean('is_active')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
