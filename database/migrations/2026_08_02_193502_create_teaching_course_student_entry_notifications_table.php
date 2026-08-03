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
        Schema::create('teaching_course_student_entry_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_course_student_entry_id');
            $table->string('recipient_type', 32);
            $table->string('recipient_label');
            $table->string('email');
            $table->timestamp('informed_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('teaching_course_student_entry_id', 'teaching_entry_notification_entry_fk')
                ->references('id')
                ->on('teaching_course_student_entries')
                ->cascadeOnDelete();

            $table->unique(
                ['teaching_course_student_entry_id', 'recipient_type', 'email'],
                'teaching_entry_notification_recipient_unique'
            );
            $table->index('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_course_student_entry_notifications');
    }
};
