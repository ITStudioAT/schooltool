<?php

use App\Models\MaterialInboxImport;
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
            if (! Schema::hasColumn('material_inbox_imports', 'import_mode')) {
                $table->string('import_mode', 16)
                    ->default(MaterialInboxImport::MODE_COPY)
                    ->after('source_material_id');
                $table->index(['target_user_id', 'import_mode'], 'material_inbox_imports_target_mode_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_inbox_imports')) {
            return;
        }

        Schema::table('material_inbox_imports', function (Blueprint $table) {
            if (Schema::hasColumn('material_inbox_imports', 'import_mode')) {
                $table->dropIndex('material_inbox_imports_target_mode_index');
                $table->dropColumn('import_mode');
            }
        });
    }
};
