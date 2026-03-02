<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingSchoolHour extends Model
{
    /** @use HasFactory<\Database\Factories\TeachingSchoolHourFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
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
}
