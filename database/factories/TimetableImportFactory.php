<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TimetableImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableImport>
 */
class TimetableImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'schoolyear_id' => fn (array $attributes) => Schoolyear::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'user_id' => fn (array $attributes) => User::factory()->create([
                'school_id' => $attributes['school_id'],
                'schoolyear_id' => $attributes['schoolyear_id'],
            ])->id,
            'original_filename' => 'stundenplan.txt',
            'stored_filename' => 'stundenplan.txt',
            'file_path' => 'app/private/test/stundenplan.txt',
            'sections' => ['TT' => 1],
            'total_lines' => 1,
            'tt_courses' => 1,
            'tt_first_date' => '2026-02-01',
            'tt_last_date' => '2026-02-01',
            'import_status' => 'completed',
            'progress_current' => 1,
            'progress_total' => 1,
            'import_message' => 'Import abgeschlossen.',
            'import_error' => null,
            'imported_at' => now(),
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
