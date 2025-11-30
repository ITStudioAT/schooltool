<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringOffer extends Model
{

    protected $guarded = [];


    protected $casts = [
        'time_table' => 'array',
        'price_per_hour' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
