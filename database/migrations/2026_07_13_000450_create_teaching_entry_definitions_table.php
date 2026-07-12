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
        Schema::create('teaching_entry_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('short_name', 2);
            $table->string('name');
            $table->string('category', 20);
            $table->boolean('has_properties')->default(false);
            $table->string('properties_mode', 10)->default('free');
            $table->json('fixed_properties')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'schoolyear_id', 'short_name'],
                'teaching_entry_definitions_user_year_short_unique'
            );
            $table->index(['school_id', 'schoolyear_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_entry_definitions');
    }
};
