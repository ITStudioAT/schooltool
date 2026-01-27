<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import116', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('class');
            $table->string('student_code');
            $table->string('last_name');
            $table->string('first_name');
            $table->string('email')->nullable();
            $table->string('phone_1')->nullable();
            $table->string('phone_2')->nullable();
            $table->string('sex')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_email')->nullable();
            $table->string('mother_phone_1')->nullable();
            $table->string('mother_phone_2')->nullable();
            $table->string('father_name')->nullable();
            $table->string('father_email')->nullable();
            $table->string('father_phone_1')->nullable();
            $table->string('father_phone_2')->nullable();
            $table->dateTime('import_date');
            $table->dateTime('exists_date')->nullable();
            $table->unsignedBigInteger('import_user_id');
            $table->timestamps();

            $table->index(['school_id', 'student_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import116');
    }
};
