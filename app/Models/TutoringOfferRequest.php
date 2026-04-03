<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property int $offer_id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property string|null $message
 * @property bool $is_serious
 * @property Carbon|null $archived_at
 * @property Carbon|null $to_user_archived_at
 * @property string|null $token
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $last_sent_at
 * @property int|null $sent_count
 * @property Carbon|null $seen_at
 * @property Carbon|null $last_seen_at
 * @property int|null $seen_count
 * @property Carbon|null $mail_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $from_user
 * @property-read TutoringOffer|null $offer
 * @property-read School|null $school
 * @property-read User|null $to_user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereFromUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereIsSerious($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereLastSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereMailAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereOfferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereSchoolId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereSeenCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereSentCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereToUserArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereToUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TutoringOfferRequest whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TutoringOfferRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_serious' => 'boolean',
        'mail_at' => 'datetime:Y-m-d H:i:s',
        'sent_at' => 'datetime:Y-m-d H:i:s',
        'seen_at' => 'datetime:Y-m-d H:i:s',
        'last_sent_at' => 'datetime:Y-m-d H:i:s',
        'last_seen_at' => 'datetime:Y-m-d H:i:s',
        'archived_at' => 'datetime:Y-m-d H:i:s',
        'to_user_archived_at' => 'datetime:Y-m-d H:i:s',
        'token_expires_at' => 'datetime:Y-m-d H:i:s',
        'token' => 'string',
    ];

    public function from_user()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to_user()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function offer()
    {
        return $this->belongsTo(TutoringOffer::class, 'offer_id');
    }
}
