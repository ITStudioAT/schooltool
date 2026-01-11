<?php

namespace App\Models;

use App\Models\Register;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @mixin IdeHelperSchoolyear
 * @mixin \Eloquent
 */
class Schoolyear extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'from',
        'until',
        'sem_2_start',
    ];


    public function hasDependencies(): bool
    {
        // Prüfen, ob es Registers gibt
        if (Register::where('schoolyear_id', $this->id)->exists())  return true;

        // Prüfen, ob es mehr als einen Uas
        if (User::where('schoolyear_id', $this->id)->exists())  return true;
        return false;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
