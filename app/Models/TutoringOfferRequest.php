<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'token_expires_at' => 'datetime:Y-m-d H:i:s',
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
