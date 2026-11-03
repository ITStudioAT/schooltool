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
        Schema::create('teaching_explanation_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('explanation', 80);
            $table->string('token', 48)->unique();
            $table->string('access_mode', 20)->default('authenticated');
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'user_id', 'explanation'], 'explanation_owner_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_explanation_shares');
    }
};
