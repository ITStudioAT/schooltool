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
        Schema::create('teaching_work_action_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('work_id');
            $table->unsignedBigInteger('course_id');
            $table->date('scheduled_date')->nullable();
            $table->json('event');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->index(['available_at', 'work_id']);
            $table->index(['work_id', 'published_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_work_action_events');
    }
};
