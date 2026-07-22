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
        Schema::table('teaching_entry_definitions', function (Blueprint $table) {
            $table->unsignedBigInteger('teaching_entry_grading_part_id')
                ->nullable()
                ->after('teaching_entry_area_id');
            $table->foreign(
                'teaching_entry_grading_part_id',
                'teaching_entry_definitions_grading_part_foreign'
            )
                ->references('id')
                ->on('teaching_entry_grading_parts')
                ->nullOnDelete();
        });
    }
};
