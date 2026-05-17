<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentTimetableSubjectRow extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'semester',
        'branch',
        'json_code',
        'json_subject',
        'name',
        'hours_per_week',
        'is_active',
        'sort_order',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'hours_per_week' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
