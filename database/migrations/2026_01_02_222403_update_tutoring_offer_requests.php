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
            if (! in_array('token', $columns, true)) {
                $table->string('token')->nullable()->after('archived_at');
            }
            if (! in_array('token_expires_at', $columns, true)) {
                $table->timestamp('token_expires_at')->nullable()->after('token');
            }
            if (! in_array('sent_at', $columns, true)) {
                $table->timestamp('sent_at')->nullable()->after('token_expires_at');
            }
            if (! in_array('last_sent_at', $columns, true)) {
                $table->timestamp('last_sent_at')->nullable()->after('sent_at');
            }
            if (! in_array('seen_at', $columns, true)) {
                $table->timestamp('seen_at')->nullable()->after('last_sent_at');
            }
            if (! in_array('last_seen_at', $columns, true)) {
                $table->timestamp('last_seen_at')->nullable()->after('seen_at');
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
            'token',
            'token_expires_at',
            'sent_at',
            'last_sent_at',
            'seen_at',
            'last_seen_at',
        ]);

        if (! empty($droppables)) {
            Schema::table('tutoring_offer_requests', function (Blueprint $table) use ($droppables) {
                $table->dropColumn($droppables);
            });
        }
    }
};
