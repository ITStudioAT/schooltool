<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentTimetableRecognitionImport extends Model
{
    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'original_filename',
        'stored_filename',
        'file_path',
        'file_size',
        'total_rows',
        'imported_rows',
        'skipped_rows',
        'import_status',
        'import_message',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'total_rows' => 'integer',
            'imported_rows' => 'integer',
            'skipped_rows' => 'integer',
            'imported_at' => 'datetime',
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

    public function rows(): HasMany
    {
        return $this->hasMany(StudentTimetableRecognitionRow::class);
    }
}
