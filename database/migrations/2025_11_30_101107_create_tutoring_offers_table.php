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
        Schema::create('tutoring_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreignId('user_id');
            $table->foreignId('subject_id');
            $table->string('title');
            $table->string('description', 1024)->nullable();
            $table->json('classes')->nullable();
            $table->json('time_table')->nullable();
            $table->date('active_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price_per_hour', 8, 2);
            $table->boolean('is_group')->default(false);
            $table->unsignedInteger('max_group_members')->nullable()->default(2);
            $table->boolean('must_be_accepted')->default(true);
            $table->string('email_mentor')->nullable();
            $table->date('accepted_at')->nullable();

            $table->unsignedInteger('click_count')->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutoring_offers');
    }
};
