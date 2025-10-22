<?php

namespace App\Models;

use App\Models\Schoolyear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Register extends Model
{

    protected $guarded = [];


    protected $casts = [
        'is_active' => 'boolean',
        'show_phone' => 'boolean',
        'must_phone' => 'boolean',
        'show_student_last_name' => 'boolean',
        'must_student_last_name' => 'boolean',
        'show_student_first_name' => 'boolean',
        'must_student_first_name' => 'boolean',
        'show_booked' => 'boolean',
        'show_end_time' => 'boolean',
        'show_supervisor' => 'boolean',
    ];

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function hasDependencies(): bool
    {
        return false;
    }
}
