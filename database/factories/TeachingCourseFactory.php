<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingCourse>
 */
class TeachingCourseFactory extends Factory
{
    protected $model = TeachingCourse::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'schoolyear_id' => Schoolyear::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->randomElement([
                'Mathematik',
                'Deutsch',
                'Englisch',
                'Physik',
                'Chemie',
                'Biologie',
                'Geschichte',
                'Geographie',
                'Informatik',
                'Sport',
            ]),
            'classes' => $this->faker->randomElements(
                ['1A', '1B', '2A', '2B', '3A', '3B', '4A', '4B', '5A', '5B'],
                $this->faker->numberBetween(1, 3)
            ),
        ];
    }

    /**
     * Set the school for the course.
     */
    public function forSchool(School $school): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => $school->id,
        ]);
    }

    /**
     * Set the schoolyear for the course.
     */
    public function forSchoolyear(Schoolyear $schoolyear): static
    {
        return $this->state(fn (array $attributes) => [
            'schoolyear_id' => $schoolyear->id,
        ]);
    }

    /**
     * Set the teacher (user) for the course.
     */
    public function forTeacher(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a course without a teacher assigned.
     */
    public function withoutTeacher(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Create a course with specific classes.
     */
    public function withClasses(array $classes): static
    {
        return $this->state(fn (array $attributes) => [
            'classes' => $classes,
        ]);
    }
}
