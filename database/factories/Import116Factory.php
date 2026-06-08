<?php

namespace Database\Factories;

use App\Models\Import116;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import116>
 */
class Import116Factory extends Factory
{
    protected $model = Import116::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'class' => $this->faker->randomElement(['1A', '1B', '2A', '2B', '3A', '3B', '4A', '4B', '5A', '5B']),
            'school_level' => $this->faker->optional()->randomElement(['1', '2', '3', '4', '5']),
            'attendance_year' => $this->faker->optional()->randomElement(['1', '2', '3', '4', '5']),
            'religion' => $this->faker->optional()->randomElement(['Rk', 'Rev', 'Ris', 'Ror', 'ETH']),
            'student_code' => $this->faker->unique()->numerify('######'),
            'last_name' => $this->faker->lastName(),
            'first_name' => $this->faker->firstName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_1' => $this->faker->optional()->phoneNumber(),
            'phone_2' => $this->faker->optional()->phoneNumber(),
            'sex' => $this->faker->randomElement(['m', 'w', null]),
            'birth_date' => $this->faker->dateTimeBetween('-18 years', '-6 years'),
            'mother_name' => $this->faker->optional()->name('female'),
            'mother_email' => $this->faker->optional()->safeEmail(),
            'mother_phone_1' => $this->faker->optional()->phoneNumber(),
            'mother_phone_2' => $this->faker->optional()->phoneNumber(),
            'father_name' => $this->faker->optional()->name('male'),
            'father_email' => $this->faker->optional()->safeEmail(),
            'father_phone_1' => $this->faker->optional()->phoneNumber(),
            'father_phone_2' => $this->faker->optional()->phoneNumber(),
            'import_date' => now(),
            'exists_date' => now(),
            'import_user_id' => User::factory(),
        ];
    }

    /**
     * Set the school for the import record.
     */
    public function forSchool(School $school): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => $school->id,
        ]);
    }

    /**
     * Set the import user.
     */
    public function importedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'import_user_id' => $user->id,
        ]);
    }

    /**
     * Mark record as no longer existing (deleted in source).
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'exists_date' => null,
        ]);
    }

    /**
     * Create a student with mother contact info.
     */
    public function withMother(): static
    {
        return $this->state(fn (array $attributes) => [
            'mother_name' => $this->faker->name('female'),
            'mother_email' => $this->faker->safeEmail(),
            'mother_phone_1' => $this->faker->phoneNumber(),
        ]);
    }

    /**
     * Create a student with father contact info.
     */
    public function withFather(): static
    {
        return $this->state(fn (array $attributes) => [
            'father_name' => $this->faker->name('male'),
            'father_email' => $this->faker->safeEmail(),
            'father_phone_1' => $this->faker->phoneNumber(),
        ]);
    }
}
