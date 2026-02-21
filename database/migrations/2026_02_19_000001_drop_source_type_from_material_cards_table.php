<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_cards')) {
            return;
        }

        if (! Schema::hasColumn('material_cards', 'source_type')) {
            return;
        }

        Schema::table('material_cards', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_cards')) {
            return;
        }

        if (Schema::hasColumn('material_cards', 'source_type')) {
            return;
        }

        Schema::table('material_cards', function (Blueprint $table) {
            $table->string('source_type', 20)->default('note')->after('title');
        });
    }
};
