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
        Schema::table('tutoring_offer_requests', function (Blueprint $table) {
            $table->integer('sent_count')->nullable()->default(0)->after('last_sent_at');
            $table->integer('seen_count')->nullable()->default(0)->after('last_seen_at');
            $table->timestamp('mail_at')->nullable()->after('seen_count');
            $table->timestamp('to_user_archived_at')->nullable()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tutoring_offer_requests', function (Blueprint $table) {
            //
        });
    }
};
