<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbaAnalysisRun extends Model
{
    use HasFactory;

    public const EXTRACTION_STATUS_MESSAGE_PREFIX = 'Extraktion';

    public const PARSEL_EXTRACTION_STATUS_MESSAGE_PREFIX = 'Extraktion 2';

    public const STATUS_STARTED = 'started';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABORTED = 'aborted';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'aba_id',
        'aba_attachment_id',
        'created_by_user_id',
        'status',
        'source_original_name',
        'source_path',
        'source_mime_type',
        'status_message',
        'error_message',
        'started_at',
        'running_at',
        'completed_at',
        'aborted_at',
        'failed_at',
        'extracted_sections_count',
        'extracted_figures_count',
        'text_length',
        'text_length_without_spaces',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'running_at' => 'datetime',
            'completed_at' => 'datetime',
            'aborted_at' => 'datetime',
            'failed_at' => 'datetime',
            'extracted_sections_count' => 'integer',
            'extracted_figures_count' => 'integer',
            'text_length' => 'integer',
            'text_length_without_spaces' => 'integer',
            'summary' => 'array',
        ];
    }

    public function aba(): BelongsTo
    {
        return $this->belongsTo(Aba::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(AbaAttachment::class, 'aba_attachment_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AbaAnalysisResult::class)->orderBy('sort_order');
    }

    public function scopeExtraction(Builder $query): Builder
    {
        return $query->where('status_message', 'like', self::EXTRACTION_STATUS_MESSAGE_PREFIX.'%');
    }

    public function scopeConventionalExtraction(Builder $query): Builder
    {
        return $query
            ->extraction()
            ->where('status_message', 'not like', self::PARSEL_EXTRACTION_STATUS_MESSAGE_PREFIX.'%');
    }

    public function scopeParselExtraction(Builder $query): Builder
    {
        return $query->where('status_message', 'like', self::PARSEL_EXTRACTION_STATUS_MESSAGE_PREFIX.'%');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_STARTED => 'gestartet',
            self::STATUS_RUNNING => 'läuft',
            self::STATUS_COMPLETED => 'abgeschlossen',
            self::STATUS_ABORTED => 'abgebrochen',
            self::STATUS_FAILED => 'Fehler',
            default => $status,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_ABORTED,
            self::STATUS_FAILED,
        ], true);
    }
}
