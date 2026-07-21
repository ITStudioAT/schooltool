<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('teaching_entry_areas')
            ->select(['id', 'school_id', 'schoolyear_id', 'user_id', 'name'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $areas): void {
                $timestamp = now();
                $gradingParts = $areas->map(fn (object $area): array => [
                    'school_id' => $area->school_id,
                    'schoolyear_id' => $area->schoolyear_id,
                    'user_id' => $area->user_id,
                    'teaching_entry_area_id' => $area->id,
                    'name' => $area->name,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all();

                DB::table('teaching_entry_grading_parts')->insertOrIgnore($gradingParts);
            });
    }
};
