<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schoolyear extends Model
{
    protected $guarded = [];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
