<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $school_id
 * @property string|null $short_name
 * @property string|null $long_name
 * @property bool $must_be_accepted
 * @property array<array-key, mixed>|null $email_mentors
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TutoringOffer> $offers
 * @property-read int|null $offers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereEmailMentors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereLongName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereMustBeAccepted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereShortName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringSubject whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TutoringSubject extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'email_mentors' => 'array',
        'must_be_accepted' => 'boolean',
    ];

    public function offers()
    {
        return $this->hasMany(TutoringOffer::class, 'subject_id');
    }

    /**
     * Prüfe ob das Subject Angebote hat
     */
    public function hasDependencies()
    {
        return $this->offers()->exists();
    }
}
