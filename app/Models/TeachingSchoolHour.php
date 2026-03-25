<?php

namespace App\Models;

use Database\Factories\TeachingSchoolHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingSchoolHour extends Model
{
    /** @use HasFactory<TeachingSchoolHourFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'hour',
        'from',
        'until',
    ];

    protected $casts = [
        'hour' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }
}
