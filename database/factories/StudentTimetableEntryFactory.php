<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentTimetableEntry>
 */
class StudentTimetableEntryFactory extends Factory
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
            'timetable_import_id' => fn (array $attributes) => TimetableImport::factory()->create([
                'school_id' => $attributes['school_id'],
                'schoolyear_id' => $attributes['schoolyear_id'],
            ])->id,
            'line_number' => $this->faker->numberBetween(1, 200),
            'date' => $this->faker->date(),
            'semester' => $this->faker->numberBetween(1, 2),
            'source_identifier' => (string) $this->faker->numberBetween(1, 9999),
            'period' => (string) $this->faker->numberBetween(1, 12),
            'starts_at' => '08:00',
            'ends_at' => '08:45',
            'subject' => $this->faker->word(),
            'class_name' => $this->faker->bothify('#?'),
            'course' => $this->faker->word(),
            'module_code' => null,
            'is_active' => true,
            'raw_columns' => ['TT'],
            'raw_line' => 'TT',
        ];
    }
}
