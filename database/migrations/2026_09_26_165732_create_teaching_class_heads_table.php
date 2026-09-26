<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_class_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('class_name');
            $table->timestamps();

            $table->unique(['school_id', 'schoolyear_id', 'user_id', 'class_name'], 'teaching_class_heads_assignment_unique');
            $table->index(['school_id', 'schoolyear_id', 'class_name'], 'teaching_class_heads_class_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_class_heads');
    }
};
