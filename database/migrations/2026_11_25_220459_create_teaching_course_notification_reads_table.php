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
        Schema::create('teaching_course_notification_reads', function (Blueprint $table) {
            $table->foreignId('teaching_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('notification_key', 64);
            $table->timestamp('read_at');
            $table->primary(['teaching_course_id', 'user_id', 'notification_key'], 'teaching_course_notification_reads_pk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_course_notification_reads');
    }
};
