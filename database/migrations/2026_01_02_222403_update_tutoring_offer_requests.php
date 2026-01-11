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
            $table->string('token')->nullable()->after('archived_at');
            $table->timestamp('token_expires_at')->nullable()->after('token');
            $table->timestamp('sent_at')->nullable()->after('token_expires_at');
            $table->timestamp('last_sent_at')->nullable()->after('sent_at');
            $table->timestamp('seen_at')->nullable()->after('last_sent_at');
            $table->timestamp('last_seen_at')->nullable()->after('seen_at');
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
