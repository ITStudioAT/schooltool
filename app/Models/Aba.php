<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Aba extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'title',
        'student_name',
        'student_class',
        'title_page_overrides',
        'created_on',
        'evaluated_on',
    ];

    protected function casts(): array
    {
        return [
            'created_on' => 'date',
            'evaluated_on' => 'date',
            'title_page_overrides' => 'array',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(AbaAttachment::class)->orderByDesc('created_at');
    }

    public function mainDocument(): HasOne
    {
        return $this->hasOne(AbaAttachment::class)
            ->where('document_kind', AbaAttachment::DOCUMENT_KIND_MAIN)
            ->orderByDesc('id');
    }

    public function additionalDocuments(): HasMany
    {
        return $this->hasMany(AbaAttachment::class)
            ->where('document_kind', AbaAttachment::DOCUMENT_KIND_ADDITIONAL)
            ->orderByDesc('id');
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany(AbaAnalysisRun::class)->orderByDesc('id');
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(AbaAnalysisRun::class)
            ->extraction()
            ->orderByDesc('id');
    }

    public function latestAnalysisRun(): HasOne
    {
        return $this->hasOne(AbaAnalysisRun::class)->latestOfMany();
    }

    public function latestExtractionRun(): HasOne
    {
        return $this->hasOne(AbaAnalysisRun::class)
            ->conventionalExtraction()
            ->latestOfMany();
    }
}
