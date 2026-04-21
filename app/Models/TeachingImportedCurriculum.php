<?php

namespace App\Models;

use Database\Factories\TeachingImportedCurriculumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingImportedCurriculum extends Model
{
    /** @use HasFactory<TeachingImportedCurriculumFactory> */
    use HasFactory;

    protected $table = 'teaching_imported_curricula';

    protected $fillable = [
        'school_id',
        'user_id',
        'adopted_curriculum_id',
        'curriculum_key',
        'title',
        'description',
        'semester_count',
        'free_weeks',
        'topics',
        'source_schema_version',
        'source_exported_at',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'free_weeks' => 'array',
            'topics' => 'array',
            'source_exported_at' => 'datetime',
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

    public function adoptedCurriculum(): BelongsTo
    {
        return $this->belongsTo(TeachingCurriculum::class, 'adopted_curriculum_id');
    }
}
