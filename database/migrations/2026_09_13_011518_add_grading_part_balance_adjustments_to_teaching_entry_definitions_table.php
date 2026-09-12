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
            $table->double('grading_part_plus_adjustment')->nullable();
            $table->double('grading_part_minus_adjustment')->nullable();
        });
    }
};
