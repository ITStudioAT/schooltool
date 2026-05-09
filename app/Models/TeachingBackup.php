<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingBackup extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'disk',
        'path',
        'filename',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
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
