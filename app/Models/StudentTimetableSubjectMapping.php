<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTimetableSubjectMapping extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'json_subject',
        'tt_subject',
        'note',
        'is_active',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
