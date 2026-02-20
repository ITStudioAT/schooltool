<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_card_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('material_card_attachments', 'source_url')) {
                $table->text('source_url')->nullable()->after('url');
            }

            if (! Schema::hasColumn('material_card_attachments', 'downloaded_at')) {
                $table->timestamp('downloaded_at')->nullable()->after('size_bytes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_card_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('material_card_attachments', 'downloaded_at')) {
                $table->dropColumn('downloaded_at');
            }

            if (Schema::hasColumn('material_card_attachments', 'source_url')) {
                $table->dropColumn('source_url');
            }
        });
    }
};
