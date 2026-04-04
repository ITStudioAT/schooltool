<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property string $last_name
 * @property string|null $first_name
 * @property string $short
 * @property string $email
 * @property string|null $token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read School|null $school
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereShort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Teacher whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Teacher extends Model
{
    protected $fillable = [
        'school_id',
        'email',
        'first_name',
        'last_name',
        'short',
        'token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];

    public function setToken($minutes): string
    {
        $this->token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->token_expires_at = now()->addMinutes($minutes);
        $this->save();

        return $this->token;
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
