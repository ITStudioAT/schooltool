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
        Schema::create('school_user_licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('licence_id')->constrained('licences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('assignment_type');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->decimal('base_price_per_year', 10, 2)->nullable();
            $table->decimal('charged_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'licence_id', 'user_id', 'assignment_type'], 'school_user_licences_unique_assignment');
            $table->index(['school_id', 'licence_id', 'assignment_type'], 'school_user_licences_assignment_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_user_licences');
    }
};
