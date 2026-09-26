<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A null user_id preserves an explicit empty class assignment over legacy course emails. */
class TeachingClassHead extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'class_name',
    ];
}
