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
        Schema::create('teaching_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->index();
            $table->foreignId('schoolyear_id')->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('scope', 16)->default('school')->index();
            $table->date('date')->index();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'schoolyear_id', 'date']);
            $table->index(['school_id', 'schoolyear_id', 'scope', 'user_id', 'date'], 'teaching_holidays_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_holidays');
    }
};

