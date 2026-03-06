<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 *
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
 *
 * @mixin IdeHelperRegisterDateBooking
 * @mixin \Eloquent
 */
class RegisterDateBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'register_id',
        'register_date_id',
        'user_id',
        'student_last_name',
        'student_first_name',
        'student_birthdate',
        'note',
        'siblings',
    ];

    protected $casts = [
        'siblings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerDate(): BelongsTo
    {
        return $this->belongsTo(RegisterDate::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
