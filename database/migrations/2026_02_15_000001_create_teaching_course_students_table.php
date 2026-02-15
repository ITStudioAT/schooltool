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
        Schema::create('teaching_course_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_course_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('import116_id')->nullable()->index();
            $table->text('comment')->nullable();
            $table->string('sem_1_grade', 50)->nullable();
            $table->string('sem_2_grade', 50)->nullable();
            $table->string('sem_grade', 50)->nullable();
            $table->string('behaviour_1_grade', 50)->nullable();
            $table->string('behaviour_2_grade', 50)->nullable();
            $table->string('behaviour_grade', 50)->nullable();
            $table->json('stars')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['teaching_course_id', 'user_id'], 'teaching_course_students_course_user_unique');
            $table->unique(['teaching_course_id', 'import116_id'], 'teaching_course_students_course_import_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_course_students');
    }
};
