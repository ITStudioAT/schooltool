<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_types') || ! Schema::hasTable('material_cards')) {
            return;
        }

        if (! Schema::hasColumn('material_types', 'user_id')) {
            return;
        }

        $validUserIds = DB::table('material_cards')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        DB::table('material_types')
            ->whereNull('user_id')
            ->delete();

        if (empty($validUserIds)) {
            DB::table('material_types')->delete();

            return;
        }

        DB::table('material_types')
            ->whereNotIn('user_id', $validUserIds)
            ->delete();
    }

    public function down(): void
    {
        // cleanup migration has no reversible data operation
    }
};
