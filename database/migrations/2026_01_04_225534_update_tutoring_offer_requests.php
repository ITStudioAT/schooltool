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
        if (! Schema::hasTable('tutoring_offer_requests')) {
            return;
        }

        $columns = Schema::getColumnListing('tutoring_offer_requests');

        Schema::table('tutoring_offer_requests', function (Blueprint $table) use ($columns) {
            if (!in_array('sent_count', $columns, true)) {
                $table->integer('sent_count')->nullable()->default(0)->after('last_sent_at');
            }
            if (!in_array('seen_count', $columns, true)) {
                $table->integer('seen_count')->nullable()->default(0)->after('last_seen_at');
            }
            if (!in_array('mail_at', $columns, true)) {
                $table->timestamp('mail_at')->nullable()->after('seen_count');
            }
            if (!in_array('to_user_archived_at', $columns, true)) {
                $table->timestamp('to_user_archived_at')->nullable()->after('archived_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tutoring_offer_requests')) {
            return;
        }

        $columns = Schema::getColumnListing('tutoring_offer_requests');
        $droppables = array_intersect($columns, [
            'sent_count',
            'seen_count',
            'mail_at',
            'to_user_archived_at',
        ]);

        if (!empty($droppables)) {
            Schema::table('tutoring_offer_requests', function (Blueprint $table) use ($droppables) {
                $table->dropColumn($droppables);
            });
        }
    }
};
