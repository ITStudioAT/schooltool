<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimetableRecognitionRow extends Model
{
    protected $fillable = [
        'student_timetable_recognition_import_id',
        'school_id',
        'schoolyear_id',
        'row_number',
        'student',
        'subject',
        'grade',
        'note',
        'colloquia',
        'module_repetitions',
        'teacher_code',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'raw_data' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(StudentTimetableRecognitionImport::class, 'student_timetable_recognition_import_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }
}
