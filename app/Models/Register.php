<?php

namespace App\Models;

use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $show_note
 * @property int $must_note
 * @property-read Collection<int, RegisterDateBooking> $bookings
 * @property-read int|null $bookings_count
 * @property-read Collection<int, RegisterDate> $dates
 * @property-read int|null $dates_count
 * @property-read School|null $school
 * @property-read Schoolyear|null $schoolyear
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
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
 *
 * @mixin IdeHelperRegister
 * @mixin \Eloquent
 */
class Register extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'name',
        'description_on_website',
        'max_registrations',
        'show_phone',
        'must_phone',
        'show_student_last_name',
        'must_student_last_name',
        'show_student_first_name',
        'must_student_first_name',
        'show_student_birthdate',
        'must_student_birthdate',
        'show_booked',
        'show_end_time',
        'show_supervisor',
        'is_active',
        'show_note',
        'must_note',
        'allow_siblings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_phone' => 'boolean',
        'must_phone' => 'boolean',
        'show_student_last_name' => 'boolean',
        'must_student_last_name' => 'boolean',
        'show_student_first_name' => 'boolean',
        'must_student_first_name' => 'boolean',
        'show_booked' => 'boolean',
        'show_end_time' => 'boolean',
        'show_supervisor' => 'boolean',
        'allow_siblings' => 'boolean',
    ];

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function dates()
    {
        return $this->hasMany(RegisterDate::class);
    }

    public function bookings()
    {
        // goes through register_dates → register_date_bookings
        return $this->hasManyThrough(
            RegisterDateBooking::class, // final model
            RegisterDate::class,        // intermediate
            'register_id',              // FK on register_dates -> registers.id
            'register_date_id',         // FK on register_date_bookings -> register_dates.id
            'id',                       // local key on registers
            'id'                        // local key on register_dates
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'register_date_bookings', // pivot table
            'register_id',            // FK on pivot to registers.id
            'user_id'                 // FK on pivot to users.id
        )
            ->withPivot('school_id', 'schoolyear_id', 'register_date_id')
            ->distinct();                // avoid duplicates when user has multiple bookings
    }

    public function hasDependencies(): bool
    {
        return $this->dates->count() != 0;
    }

    protected function descriptionOnWebsite(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
            set: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
        );
    }
}
