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
        if (Schema::hasTable('teaching_entry_grading_parts')) {
            if (! Schema::hasIndex('teaching_entry_grading_parts', 'grading_parts_school_year_user_index')) {
                Schema::table('teaching_entry_grading_parts', function (Blueprint $table) {
                    $table->index(
                        ['school_id', 'schoolyear_id', 'user_id'],
                        'grading_parts_school_year_user_index'
                    );
                });
            }

            if (! Schema::hasIndex('teaching_entry_grading_parts', 'teaching_entry_grading_parts_user_year_area_name_unique')) {
                Schema::table('teaching_entry_grading_parts', function (Blueprint $table) {
                    $table->unique(
                        ['user_id', 'schoolyear_id', 'teaching_entry_area_id', 'name'],
                        'teaching_entry_grading_parts_user_year_area_name_unique'
                    );
                });
            }

            return;
        }

        Schema::create('teaching_entry_grading_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_entry_area_id')->constrained('teaching_entry_areas')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->index(
                ['school_id', 'schoolyear_id', 'user_id'],
                'grading_parts_school_year_user_index'
            );
            $table->unique(
                ['user_id', 'schoolyear_id', 'teaching_entry_area_id', 'name'],
                'teaching_entry_grading_parts_user_year_area_name_unique'
            );
        });
    }
};
