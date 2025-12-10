<?php

namespace App\Models;

use App\Models\TutoringSubject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

class TutoringOffer extends Model
{

    protected $guarded = [];


    protected $casts = [
        'classes' => AsArrayObject::class,
        'time_table' => 'array',
        'price_per_hour' => 'decimal:2',
        'is_active' => 'boolean',
        'must_be_accepted' => 'boolean'
    ];

    public function subject()
    {
        return $this->belongsTo(TutoringSubject::class, 'subject_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
