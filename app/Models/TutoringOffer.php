<?php

namespace App\Models;

use App\Models\School;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property int $subject_id
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Database\Eloquent\Casts\ArrayObject<array-key, mixed> $classes
 * @property array<array-key, mixed>|null $time_table
 * @property string|null $active_until
 * @property bool $is_active
 * @property numeric $price_per_hour
 * @property int $is_group
 * @property int|null $max_group_members
 * @property bool $must_be_accepted
 * @property string|null $email_mentor
 * @property string|null $accepted_at
 * @property int|null $click_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read TutoringSubject|null $subject
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereActiveUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereClasses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereClickCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereEmailMentor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereIsGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereMaxGroupMembers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereMustBeAccepted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer wherePricePerHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereTimeTable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOffer whereUserId($value)
 * @mixin \Eloquent
 */
class TutoringOffer extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'subject_id',
        'title',
        'description',
        'classes',
        'time_table',
        'active_until',
        'is_active',
        'price_per_hour',
        'is_group',
        'max_group_members',
        'must_be_accepted',
        'email_mentor',
        'click_count',
    ];

    // Protected fields: accepted_at (managed by admin approval process)

    protected $casts = [
        'classes' => AsArrayObject::class,
        'time_table' => 'array',
        'price_per_hour' => 'decimal:2',
        'is_active' => 'boolean',
        'must_be_accepted' => 'boolean',
        'select_only_me_concerning' => 'nullable|boolean',
        'visible_for_other_schools' => 'boolean',
        'click_ips' => 'array',
    ];

    public function subject()
    {
        return $this->belongsTo(TutoringSubject::class, 'subject_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
