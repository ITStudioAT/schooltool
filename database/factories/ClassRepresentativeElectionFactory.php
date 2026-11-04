<?php

namespace Database\Factories;

use App\Models\ClassRepresentativeElection;
use App\Models\School;
use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassRepresentativeElection>
 */
class ClassRepresentativeElectionFactory extends Factory
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
            'schoolyear_id' => Schoolyear::factory(),
            'class_name' => '1A',
            'announced_at' => null,
        ];
    }
}
