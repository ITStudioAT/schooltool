<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableImport extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'sections',
        'total_lines',
        'tt_courses',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'total_lines' => 'integer',
            'tt_courses' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
