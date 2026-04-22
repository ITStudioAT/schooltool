<?php

namespace App\Models;

use App\Services\TeachingCourseService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TeachingCourse extends Model
{
    use HasFactory;

    protected mixed $pendingStudentsPayload = null;

    protected mixed $pendingStudentsDeletedPayload = null;

    protected ?array $runtimeStudentsPayload = null;

    protected ?array $runtimeStudentsDeletedPayload = null;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'title',
        'description',
        'classes',
        'students',
        'students_deleted',
        'reminder',
        'teaching_schema_id',
        'teaching_curriculum_id',
    ];

    protected $casts = [
        'classes' => 'array',
        'reminder' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $course) {
            if ($course->pendingStudentsPayload === null && $course->pendingStudentsDeletedPayload === null) {
                return;
            }

            app(TeachingCourseService::class)->syncCourseStudents(
                $course,
                $course->pendingStudentsPayload ?? [],
                $course->pendingStudentsDeletedPayload ?? []
            );

            $course->pendingStudentsPayload = null;
            $course->pendingStudentsDeletedPayload = null;
            $course->runtimeStudentsPayload = null;
            $course->runtimeStudentsDeletedPayload = null;
            $course->unsetRelation('teachingCourseStudents');
            $course->unsetRelation('teachingCourseStudentsWithTrashed');
        });
    }

    public function hasDependencies(): bool
    {
        return $this->teachingCourseStudents()->withTrashed()->exists();
    }

    public function setStudentsAttribute(mixed $value): void
    {
        $this->pendingStudentsPayload = $value;
        $this->runtimeStudentsPayload = $this->normalizeLegacyStudentPayload($value);
    }

    public function getStudentsAttribute(mixed $value): array
    {
        if ($this->runtimeStudentsPayload !== null) {
            return $this->runtimeStudentsPayload;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $rows = $this->relationLoaded('teachingCourseStudents')
            ? $this->teachingCourseStudents
            : $this->teachingCourseStudents()->get();

        return $rows
            ->map(fn (TeachingCourseStudent $student) => $student->toLegacyPayloadArray())
            ->values()
            ->all();
    }

    public function setStudentsDeletedAttribute(mixed $value): void
    {
        $this->pendingStudentsDeletedPayload = $value;
        $this->runtimeStudentsDeletedPayload = $this->normalizeLegacyStudentPayload($value);
    }

    public function getStudentsDeletedAttribute(mixed $value): array
    {
        if ($this->runtimeStudentsDeletedPayload !== null) {
            return $this->runtimeStudentsDeletedPayload;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $rows = $this->relationLoaded('teachingCourseStudentsWithTrashed')
            ? $this->teachingCourseStudentsWithTrashed->filter(fn (TeachingCourseStudent $student) => $student->trashed())
            : $this->teachingCourseStudents()->onlyTrashed()->get();

        return $rows
            ->map(fn (TeachingCourseStudent $student) => $student->studentId())
            ->filter(fn ($id) => $id !== null)
            ->values()
            ->all();
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

    public function teachingCurriculum(): BelongsTo
    {
        return $this->belongsTo(TeachingCurriculum::class);
    }

    public function teachingCourseDates(): HasMany
    {
        return $this->hasMany(TeachingCourseDate::class);
    }

    public function teachingCourseWorks(): HasMany
    {
        return $this->hasMany(TeachingCourseWork::class);
    }

    public function teachingCourseStudentEntries(): HasMany
    {
        return $this->hasMany(TeachingCourseStudentEntry::class);
    }

    public function teachingCourseStudents(): HasMany
    {
        return $this->hasMany(TeachingCourseStudent::class);
    }

    public function teachingCourseStudentsWithTrashed(): HasMany
    {
        return $this->hasMany(TeachingCourseStudent::class)->withTrashed();
    }

    private function normalizeLegacyStudentPayload(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Collection) {
            return $value->all();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
