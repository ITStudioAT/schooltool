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
        DB::table('teaching_schemas')
            ->whereNotNull('user_id')
            ->whereNotNull('school_id')
            ->whereNotNull('schoolyear_id')
            ->orderBy('id')
            ->chunkById(100, function ($schemas): void {
                foreach ($schemas as $schema) {
                    $name = trim((string) $schema->name) ?: 'Allgemein';

                    DB::table('teaching_entry_areas')->insertOrIgnore([
                        'school_id' => $schema->school_id,
                        'schoolyear_id' => $schema->schoolyear_id,
                        'user_id' => $schema->user_id,
                        'name' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        DB::table('teaching_entry_definitions')
            ->whereNull('teaching_entry_area_id')
            ->orderBy('id')
            ->chunkById(100, function ($entryDefinitions): void {
                foreach ($entryDefinitions as $entryDefinition) {
                    $schemaName = DB::table('teaching_schemas')
                        ->where('user_id', $entryDefinition->user_id)
                        ->where('schoolyear_id', $entryDefinition->schoolyear_id)
                        ->where('schema_id', $entryDefinition->teaching_schema_id)
                        ->value('name');
                    $areaName = trim((string) $schemaName) ?: 'Allgemein';
                    $areaId = DB::table('teaching_entry_areas')
                        ->where('user_id', $entryDefinition->user_id)
                        ->where('schoolyear_id', $entryDefinition->schoolyear_id)
                        ->where('name', $areaName)
                        ->value('id');

                    if (! $areaId) {
                        $areaId = DB::table('teaching_entry_areas')->insertGetId([
                            'school_id' => $entryDefinition->school_id,
                            'schoolyear_id' => $entryDefinition->schoolyear_id,
                            'user_id' => $entryDefinition->user_id,
                            'name' => $areaName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('teaching_entry_definitions')
                        ->where('id', $entryDefinition->id)
                        ->update(['teaching_entry_area_id' => $areaId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
