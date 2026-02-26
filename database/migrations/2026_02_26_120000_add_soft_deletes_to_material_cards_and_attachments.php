<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('material_cards', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('material_card_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('material_card_attachments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_card_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('material_card_attachments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('material_cards', function (Blueprint $table) {
            if (Schema::hasColumn('material_cards', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
