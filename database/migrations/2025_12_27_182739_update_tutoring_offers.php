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
        if (! Schema::hasTable('tutoring_offers')) {
            return;
        }

        if (! Schema::hasColumn('tutoring_offers', 'click_ips')) {
            Schema::table('tutoring_offers', function (Blueprint $table) {
                $table->json('click_ips')->nullable()->after('click_count');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tutoring_offers')) {
            return;
        }

        if (Schema::hasColumn('tutoring_offers', 'click_ips')) {
            Schema::table('tutoring_offers', function (Blueprint $table) {
                $table->dropColumn('click_ips');
            });
        }
    }
};
