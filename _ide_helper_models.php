<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string|null $name
 * @property string|null $long_name
 * @property int|null $price_per_year
 * @property int $is_selectable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereIsSelectable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereLongName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence wherePricePerYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Licence whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperLicence
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\School> $schools
 * @property-read int|null $schools_count
 */
	class Licence extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $dispatched_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereDispatchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueTest whereUserId($value)
 * @mixin \Eloquent
 * @mixin IdeHelperQueueTest
 */
	class QueueTest extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property int $schoolyear_id
 * @property string|null $name
 * @property string|null $description_on_website
 * @property int $max_registrations
 * @property bool $show_phone
 * @property bool $must_phone
 * @property bool $show_student_last_name
 * @property bool $must_student_last_name
 * @property bool $show_student_first_name
 * @property bool $must_student_first_name
 * @property int $show_student_birthdate
 * @property int $must_student_birthdate
 * @property bool $show_booked
 * @property bool $show_end_time
 * @property bool $show_supervisor
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $show_note
 * @property int $must_note
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RegisterDateBooking> $bookings
 * @property-read int|null $bookings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RegisterDate> $dates
 * @property-read int|null $dates_count
 * @property-read School|null $school
 * @property-read Schoolyear|null $schoolyear
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\RegisterFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereDescriptionOnWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereMaxRegistrations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereMustNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereMustPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereMustStudentBirthdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereMustStudentFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereMustStudentLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereSchoolyearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowBooked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowStudentBirthdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowStudentFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowStudentLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereShowSupervisor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Register whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperRegister
 */
	class Register extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property int $schoolyear_id
 * @property int $register_id
 * @property string|null $supervisor
 * @property string $date
 * @property string $from
 * @property string $to
 * @property int $max_registrations
 * @property int $is_locked
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RegisterDateBooking> $bookings
 * @property-read int|null $bookings_count
 * @method static \Database\Factories\RegisterDateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereMaxRegistrations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereRegisterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereSchoolyearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereSupervisor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDate whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperRegisterDate
 */
	class RegisterDate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property int $schoolyear_id
 * @property int $register_id
 * @property int $register_date_id
 * @property int $user_id
 * @property string|null $student_last_name
 * @property string|null $student_first_name
 * @property string|null $student_birthdate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $note
 * @property-read Register|null $register
 * @property-read RegisterDate|null $registerDate
 * @property-read School|null $school
 * @property-read User|null $user
 * @method static \Database\Factories\RegisterDateBookingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereRegisterDateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereRegisterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereSchoolyearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereStudentBirthdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereStudentFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereStudentLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegisterDateBooking whereUserId($value)
 * @mixin \Eloquent
 * @mixin IdeHelperRegisterDateBooking
 */
	class RegisterDateBooking extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 * @mixin \Eloquent
 * @mixin IdeHelperRole
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $long_name
 * @property string|null $short_name
 * @property string|null $email
 * @property string|null $logo
 * @property int $is_selectable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Schoolyear|null $activeSchoolyear
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Licence> $licences
 * @property-read int|null $licences_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Register> $registers
 * @property-read int|null $registers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Schoolyear> $schoolyears
 * @property-read int|null $schoolyears_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\SchoolFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School selectables()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereIsSelectable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereLongName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|School whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperSchool
 */
	class School extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property int $licence_id
 * @property string|null $valid_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereLicenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolLicence whereValidUntil($value)
 * @mixin \Eloquent
 * @mixin IdeHelperSchoolLicence
 * @property-read \App\Models\Licence|null $licence
 * @property-read \App\Models\School|null $school
 */
	class SchoolLicence extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolTool whereUpdatedAt($value)
 */
	class SchoolTool extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property string|null $name
 * @property string|null $from
 * @property string|null $until
 * @property string|null $sem_2_start
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear active()
 * @method static \Database\Factories\SchoolyearFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereSem2Start($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Schoolyear whereUpdatedAt($value)
 * @mixin \Eloquent
 * @mixin IdeHelperSchoolyear
 */
	class Schoolyear extends \Eloquent {}
}



namespace App\Models{
/**
 * @property int $id
 * @property int $school_id
 * @property int|null $schoolyear_id
 * @property int|null $register_id
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $last_name
 * @property string|null $first_name
 * @property string|null $phone
 * @property string|null $login_at
 * @property string|null $login_ip
 * @property int|null $is_2fa
 * @property string|null $token_2fa
 * @property \Illuminate\Support\Carbon|null $token_2fa_expires_at
 * @property string|null $token_2fa_2
 * @property string|null $token_2fa_2_expires_at
 * @property string|null $email_2fa
 * @property string|null $email_2fa_verified_at
 * @property int|null $is_active
 * @property string|null $register_started_at
 * @property string|null $register_as
 * @property string|null $confirmed_at
 * @property string|null $uuid
 * @property string|null $uuid_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RegisterDateBooking> $registerDateBookings
 * @property-read int|null $register_date_bookings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read Register|null $selectedRegister
 * @property-read School|null $selectedSchool
 * @property-read Schoolyear|null $selectedSchoolyear
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail2fa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail2faVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIs2fa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisterAs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisterStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSchoolyearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2fa($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2fa2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2fa2ExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereToken2faExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 * @mixin IdeHelperUser
 * @property string|null $sex
 * @property string $schoolclass
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User bySchoolAndRole($schoolId, $roleName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSchoolclass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSex($value)
 */
	class User extends \Eloquent {}
}
