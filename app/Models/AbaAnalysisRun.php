<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbaAnalysisRun extends Model
{
    use HasFactory;

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
