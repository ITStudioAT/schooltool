<?php

namespace App\Models;

use Database\Factories\StudentTimetableEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableEntry extends Model
{
    /** @use HasFactory<StudentTimetableEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'timetable_import_id',
        'line_number',
        'date',
        'semester',
        'source_identifier',
        'period',
        'subject',
        'teacher',
        'room',
        'class_name',
        'course',
        'student_group',
        'raw_columns',
        'raw_line',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'semester' => 'integer',
            'line_number' => 'integer',
            'raw_columns' => 'array',
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

    public function timetableImport(): BelongsTo
    {
        return $this->belongsTo(TimetableImport::class);
    }
}
