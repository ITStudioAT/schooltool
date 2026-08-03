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
        Schema::table('teaching_course_student_entry_notifications', function (Blueprint $table) {
            $table->string('confirmation_method', 16)->nullable()->after('confirmed_at');
            $table->foreignId('confirmed_by_user_id')->nullable()->after('confirmation_method');
            $table->string('confirmed_by_label')->nullable()->after('confirmed_by_user_id');

            $table->foreign('confirmed_by_user_id', 'teaching_entry_notification_confirmer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
