<?php

namespace App\Models;

use App\Support\SafeHtml;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingCourseWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_course_id',
        'type',
        'title',
        'description',
        'is_group_work',
        'group_size',
        'is_random_groups',
        'date_for_all_groups',
        'finish_until_date',
        'finish_until_time',
        'maximum_plus',
        'groups',
        'status',
    ];

    protected $casts = [
        'date_for_all_groups' => 'date:Y-m-d',
        'finish_until_date' => 'date:Y-m-d',
        'is_group_work' => 'boolean',
        'group_size' => 'integer',
        'maximum_plus' => 'integer',
        'is_random_groups' => 'boolean',
        'groups' => 'array',
        'status' => 'array',
    ];

    public function teachingCourse(): BelongsTo
    {
        return $this->belongsTo(TeachingCourse::class);
    }

    public function automationEnabled(): bool
    {
        return ($this->status['automation_enabled'] ?? true) !== false;
    }

    /** @return array{at: string, date: string, time: string}|null */
    public function communicatedDeadline(): ?array
    {
        $value = $this->status['communicated_deadline_at'] ?? null;
        if (! is_string($value) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00[+-]\d{2}:\d{2}\z/', $value)) {
            return null;
        }
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        if ($instant === false || $instant->format('Y-m-d\TH:i:sP') !== $value) {
            return null;
        }
        $instant = $instant->setTimezone(new DateTimeZone('Europe/Vienna'));

        return ['at' => $instant->format('Y-m-d\TH:i:sP'), 'date' => $instant->format('Y-m-d'), 'time' => $instant->format('H:i')];
    }

    public function teachingCourseStudentEntries(): HasMany
    {
        return $this->hasMany(TeachingCourseStudentEntry::class);
    }

    public function teachingCourseWorkGroupStudents(): HasMany
    {
        return $this->hasMany(TeachingCourseWorkGroupStudent::class);
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
            set: fn (?string $value): string => app(SafeHtml::class)->sanitize($value),
        );
    }
}
