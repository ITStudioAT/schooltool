<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('teaching_entry_definitions')
            ->whereNull('teaching_schema_id')
            ->orderBy('id')
            ->chunkById(100, function ($entryDefinitions): void {
                foreach ($entryDefinitions as $entryDefinition) {
                    $schemaId = DB::table('teaching_schemas')
                        ->where('user_id', $entryDefinition->user_id)
                        ->where('schoolyear_id', $entryDefinition->schoolyear_id)
                        ->orderByRaw("CASE WHEN name = 'Standard' THEN 0 ELSE 1 END")
                        ->orderBy('id')
                        ->value('schema_id');

                    if (! $schemaId) {
                        continue;
                    }

                    DB::table('teaching_entry_definitions')
                        ->where('id', $entryDefinition->id)
                        ->update(['teaching_schema_id' => $schemaId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
