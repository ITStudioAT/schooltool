<?php

namespace App\Models;

use Database\Factories\TimetableImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableImport extends Model
{
    /** @use HasFactory<TimetableImportFactory> */
    use HasFactory;

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
        'tt_first_date',
        'tt_last_date',
        'import_status',
        'progress_current',
        'progress_total',
        'import_message',
        'import_error',
        'imported_at',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'total_lines' => 'integer',
            'tt_courses' => 'integer',
            'tt_first_date' => 'date:Y-m-d',
            'tt_last_date' => 'date:Y-m-d',
            'progress_current' => 'integer',
            'progress_total' => 'integer',
            'imported_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function entries(): HasMany
    {
        return $this->hasMany(StudentTimetableEntry::class);
    }
}
