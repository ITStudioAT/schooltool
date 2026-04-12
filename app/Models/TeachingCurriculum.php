<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCurriculum extends Model
{
    protected $table = 'teaching_curricula';

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'title',
        'description',
        'semester_count',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
