<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_inbox_imports')) {
            return;
        }

        Schema::table('material_inbox_imports', function (Blueprint $table) {
            $table->dropUnique('material_inbox_imports_target_source_unique');
            $table->unique(
                ['target_user_id', 'target_material_card_id'],
                'material_inbox_imports_target_card_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_inbox_imports')) {
            return;
        }

        Schema::table('material_inbox_imports', function (Blueprint $table) {
            $table->dropUnique('material_inbox_imports_target_card_unique');
            $table->unique(
                ['target_user_id', 'source_school_id', 'source_material_id'],
                'material_inbox_imports_target_source_unique'
            );
        });
    }
};
