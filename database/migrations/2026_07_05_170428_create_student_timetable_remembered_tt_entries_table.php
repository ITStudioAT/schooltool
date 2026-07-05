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
        Schema::create('student_timetable_remembered_tt_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('schoolyear_id')->constrained('schoolyears')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('offer_key_hash', 64);
            $table->char('entry_key_hash', 64);
            $table->text('offer_key');
            $table->text('entry_key');
            $table->string('offer_name');
            $table->string('offer_schedule_label')->nullable();
            $table->string('entry_date_label')->nullable();
            $table->date('entry_date')->nullable();
            $table->string('entry_schedule_label')->nullable();
            $table->string('entry_rooms_label')->nullable();
            $table->time('entry_time_from')->nullable();
            $table->time('entry_time_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['school_id', 'schoolyear_id', 'user_id', 'offer_key_hash', 'entry_key_hash'],
                'student_tt_remembered_entries_unique',
            );
            $table->index(
                ['school_id', 'schoolyear_id', 'user_id', 'offer_key_hash'],
                'student_tt_remembered_entries_offer_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_timetable_remembered_tt_entries');
    }
};
