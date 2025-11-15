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
        Schema::create('schoolyears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->string('name')->nullable();
            $table->date('from')->nullable();
            $table->date('until')->nullable();
            $table->date('sem_2_start')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schoolyears');
    }
};
