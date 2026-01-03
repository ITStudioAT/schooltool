<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringOfferRequest extends Model
{
    protected $guarded = [];


    protected $casts = [
        'is_serious' => 'boolean',
    ];

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
