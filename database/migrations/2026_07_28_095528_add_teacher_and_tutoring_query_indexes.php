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
        Schema::table('teachers', function (Blueprint $table): void {
            $table->index(['school_id', 'email'], 'teachers_school_email_index');
            $table->index('email', 'teachers_email_index');
            $table->index(['school_id', 'short', 'last_name'], 'teachers_school_sort_index');
        });

        Schema::table('tutoring_subjects', function (Blueprint $table): void {
            $table->index(['school_id', 'short_name'], 'tutoring_subjects_school_short_index');
        });

        Schema::table('tutoring_offers', function (Blueprint $table): void {
            $table->index(
                ['school_id', 'is_active', 'accepted_at', 'active_until'],
                'tutoring_offers_public_index',
            );
            $table->index(
                ['visible_for_other_schools', 'is_active', 'accepted_at', 'active_until'],
                'tutoring_offers_cross_school_index',
            );
            $table->index(['user_id', 'created_at'], 'tutoring_offers_user_created_index');
            $table->index('subject_id', 'tutoring_offers_subject_index');
            $table->index(
                ['school_id', 'email_mentor', 'is_active'],
                'tutoring_offers_school_mentor_active_index',
            );
        });

        Schema::table('tutoring_offer_requests', function (Blueprint $table): void {
            $table->index(['school_id', 'created_at'], 'tutoring_requests_school_created_index');
            $table->index(['offer_id', 'from_user_id'], 'tutoring_requests_offer_sender_index');
            $table->index(
                ['from_user_id', 'archived_at', 'sent_at'],
                'tutoring_requests_sender_inbox_index',
            );
            $table->index(
                ['to_user_id', 'to_user_archived_at', 'sent_at'],
                'tutoring_requests_recipient_inbox_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tutoring_offer_requests', function (Blueprint $table): void {
            $table->dropIndex('tutoring_requests_recipient_inbox_index');
            $table->dropIndex('tutoring_requests_sender_inbox_index');
            $table->dropIndex('tutoring_requests_offer_sender_index');
            $table->dropIndex('tutoring_requests_school_created_index');
        });

        Schema::table('tutoring_offers', function (Blueprint $table): void {
            $table->dropIndex('tutoring_offers_school_mentor_active_index');
            $table->dropIndex('tutoring_offers_subject_index');
            $table->dropIndex('tutoring_offers_user_created_index');
            $table->dropIndex('tutoring_offers_cross_school_index');
            $table->dropIndex('tutoring_offers_public_index');
        });

        Schema::table('tutoring_subjects', function (Blueprint $table): void {
            $table->dropIndex('tutoring_subjects_school_short_index');
        });

        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropIndex('teachers_school_sort_index');
            $table->dropIndex('teachers_email_index');
            $table->dropIndex('teachers_school_email_index');
        });
    }
};
