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
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->string('due_time', 5)->nullable()->after('due_date');
            $table->timestamp('reminder_email_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_course_behaviour_entries', function (Blueprint $table) {
            $table->dropColumn(['due_time', 'reminder_email_sent_at']);
        });
    }
};
