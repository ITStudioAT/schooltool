<?php

namespace App\Models;

use App\Models\RegisterDateBooking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
class RegisterDate extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    public function bookings(): HasMany
    {
        return $this->hasMany(RegisterDateBooking::class);
    }
}
