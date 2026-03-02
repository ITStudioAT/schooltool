<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teaching_school_hours')) {
            return;
        }

        Schema::create('teaching_school_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedInteger('hour');
            $table->time('from');
            $table->time('until');
            $table->timestamps();

            $table->unique(['school_id', 'hour'], 'teaching_school_hours_school_hour_unique');
            $table->index(['school_id', 'hour'], 'teaching_school_hours_school_hour_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_school_hours');
    }
};
