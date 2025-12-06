<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringOffer extends Model
{

    protected $guarded = [];


    protected $casts = [
        'classes' => 'array',
        'time_table' => 'array',
        'price_per_hour' => 'decimal:2',
        'is_active' => 'boolean',
        'must_be_accepted' => 'boolean'
    ];

    public function subject()
    {
        return $this->belongsTo(TutoringSubject::class, 'subject_id');
    }
}
