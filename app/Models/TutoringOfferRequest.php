<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringOfferRequest extends Model
{
    protected $guarded = [];


    protected $casts = [
        'is_serious' => 'boolean',
    ];
}
