<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TeachingCurriculum extends Model
{
    protected $table = 'teaching_curricula';

    protected $attributes = [
        'is_finished' => false,
    ];

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'title',
        'description',
        'is_finished',
        'export_key',
        'topics',
    ];

    protected $hidden = [
        'semester_count',
    ];

    protected static function booted(): void
    {
        static::creating(function (TeachingCurriculum $curriculum): void {
            if (blank($curriculum->export_key)) {
                $curriculum->export_key = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_finished' => 'boolean',
        ];
    }

    protected function topics(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): array => $this->normalizeTopics($this->decodeTopics($value)),
            set: fn (mixed $value): string => json_encode(
                $this->normalizeTopics(is_array($value) ? $value : []),
                JSON_THROW_ON_ERROR
            ),
        );
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

    public function documents(): HasMany
    {
        return $this->hasMany(TeachingCurriculumDocument::class);
    }

    public function ensureExportKey(): string
    {
        if (filled($this->export_key)) {
            return (string) $this->export_key;
        }

        $this->forceFill([
            'export_key' => (string) Str::uuid(),
        ])->saveQuietly();

        return (string) $this->export_key;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeTopics(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, mixed>  $topics
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTopics(array $topics): array
    {
        return collect($topics)
            ->filter(fn (mixed $topic): bool => is_array($topic))
            ->map(function (array $topic, int $topicIndex): array {
                return [
                    'id' => filled($topic['id'] ?? null) ? (string) $topic['id'] : "topic-{$topicIndex}",
                    'title' => trim((string) ($topic['title'] ?? '')),
                    'units' => collect(is_array($topic['units'] ?? null) ? $topic['units'] : [])
                        ->filter(fn (mixed $unit): bool => is_array($unit))
                        ->map(fn (array $unit, int $unitIndex): array => [
                            'id' => filled($unit['id'] ?? null) ? (string) $unit['id'] : "unit-{$topicIndex}-{$unitIndex}",
                            'title' => trim((string) ($unit['title'] ?? '')),
                            'is_exam' => (bool) ($unit['is_exam'] ?? false),
                            'materials' => array_values(is_array($unit['materials'] ?? null) ? $unit['materials'] : []),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
