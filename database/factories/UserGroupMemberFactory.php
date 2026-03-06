<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserGroupMember>
 */
class UserGroupMemberFactory extends Factory
{
    protected $model = UserGroupMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_group_id' => UserGroup::factory(),
            'school_id' => School::factory(),
            'member_provider' => UserGroupMember::PROVIDER_USER,
            'member_ref' => 'user:'.fake()->unique()->numberBetween(1, 999999),
            'linked_user_id' => User::factory(),
            'source_schoolyear_id' => null,
            'display_name' => fake()->name(),
            'display_email' => fake()->safeEmail(),
            'display_phone' => fake()->phoneNumber(),
            'display_schoolclass' => fake()->optional()->randomElement(['1A', '2B', '3C']),
            'display_children_label' => null,
            'member_type_label' => 'Benutzer',
            'source_status' => UserGroupMember::SOURCE_STATUS_ACTIVE,
            'linked_user_status' => UserGroupMember::LINKED_USER_STATUS_LINKED,
            'meta' => null,
            'added_by_user_id' => User::factory(),
        ];
    }
}
