<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableProfileSelection extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'selection',
    ];

    protected function casts(): array
    {
        return [
            'selection' => 'array',
        ];
    }

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
