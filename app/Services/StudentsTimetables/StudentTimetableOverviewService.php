<?php

namespace App\Services\StudentsTimetables;

use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableOverviewSelection;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StudentTimetableOverviewService
{
    private const BLOCK_EDGE_TOLERANCE_DAYS = 14;

    private const CACHE_TTL_MINUTES = 30;

    private const CACHE_VERSION = 3;

    /**
     * @return list<array<string, mixed>>
     */
    public function courseGroupsForUser(User $authUser): array
    {
        $schoolyearId = $this->resolveSchoolyearIdForUser($authUser);
        $schoolyear = Schoolyear::query()
            ->where('school_id', $authUser->school_id)
            ->findOrFail($schoolyearId);

        return Cache::remember(
            self::cacheKey((int) $authUser->school_id, $schoolyearId),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->buildCourseGroups((int) $authUser->school_id, $schoolyearId, $schoolyear),
        );
    }

    public static function forgetCacheFor(int $schoolId, int $schoolyearId): void
    {
        Cache::forget(self::cacheKey($schoolId, $schoolyearId));
    }

    /**
     * @return array{course_group_keys: list<string>, courses: list<array<string, ?string>>}
     */
    public function selectedCourseGroupsForUser(User $authUser): array
    {
        $schoolyearId = $this->resolveSchoolyearIdForUser($authUser);
        $courseGroupsByKey = collect($this->courseGroupsForUser($authUser))->keyBy('key');

        $selections = StudentTimetableOverviewSelection::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $schoolyearId)
            ->where('user_id', $authUser->id)
            ->orderBy('course_label')
            ->get()
            ->filter(fn (StudentTimetableOverviewSelection $selection): bool => $courseGroupsByKey->has($selection->course_group_key))
            ->values();

        return [
            'course_group_keys' => $selections
                ->pluck('course_group_key')
                ->values()
                ->all(),
            'courses' => $selections
                ->map(fn (StudentTimetableOverviewSelection $selection): array => [
                    'course_group_key' => $selection->course_group_key,
                    'course_label' => $selection->course_label,
                    'course_title' => $selection->course_title,
                ])
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $courseGroupKeys
     * @return array{course_group_keys: list<string>, courses: list<array<string, ?string>>}
     */
    public function updateSelectedCourseGroupsForUser(User $authUser, array $courseGroupKeys): array
    {
        $schoolyearId = $this->resolveSchoolyearIdForUser($authUser);
        $courseGroupsByKey = collect($this->courseGroupsForUser($authUser))->keyBy('key');
        $selectedCourseGroups = collect($courseGroupKeys)
            ->filter()
            ->unique()
            ->map(fn (string $courseGroupKey): ?array => $courseGroupsByKey->get($courseGroupKey))
            ->filter()
            ->values();

        DB::transaction(function () use ($authUser, $schoolyearId, $selectedCourseGroups): void {
            $query = StudentTimetableOverviewSelection::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $schoolyearId)
                ->where('user_id', $authUser->id);

            $selectedCourseGroupKeys = $selectedCourseGroups
                ->pluck('key')
                ->filter()
                ->values()
                ->all();

            if ($selectedCourseGroupKeys === []) {
                $query->delete();

                return;
            }

            $query
                ->whereNotIn('course_group_key', $selectedCourseGroupKeys)
                ->delete();

            $selectedCourseGroups->each(function (array $courseGroup) use ($authUser, $schoolyearId): void {
                StudentTimetableOverviewSelection::query()->updateOrCreate(
                    [
                        'school_id' => $authUser->school_id,
                        'schoolyear_id' => $schoolyearId,
                        'user_id' => $authUser->id,
                        'course_group_key' => $courseGroup['key'],
                    ],
                    [
                        'course_label' => $courseGroup['display_label'] ?? $courseGroup['title'] ?? null,
                        'course_title' => $courseGroup['title'] ?? null,
                    ],
                );
            });
        });

        return $this->selectedCourseGroupsForUser($authUser);
    }

    private static function cacheKey(int $schoolId, int $schoolyearId): string
    {
        return 'students-timetables:overview:course-groups:v'.self::CACHE_VERSION.":{$schoolId}:{$schoolyearId}";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCourseGroups(int $schoolId, int $schoolyearId, Schoolyear $schoolyear): array
    {
        $subjectMappings = $this->activeSubjectMappings($schoolId, $schoolyearId);

        return DB::table('student_timetable_entries')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->whereNotNull('date')
            ->whereIn('semester', [1, 2])
            ->orderBy('date')
            ->orderBy('period')
            ->get([
                'id',
                'date',
                'semester',
                'period',
                'subject',
                'module_code',
                'teacher',
                'room',
                'class_name',
                'course',
                'student_group',
            ])
            ->map(fn (object $entry): ?array => $this->entryPayload($entry))
            ->filter()
            ->reject(fn (array $entry): bool => $this->isHiddenCourse($entry))
            ->groupBy(fn (array $entry): string => $this->groupKey($entry))
            ->map(fn (Collection $entries): array => $this->groupPayload($entries, $schoolyear, $subjectMappings))
            ->values()
            ->sortBy([
                ['semester', 'asc'],
                ['weekday', 'asc'],
                ['hour', 'asc'],
                ['title', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{json_subject: string, tt_subject: string}>
     */
    private function activeSubjectMappings(int $schoolId, int $schoolyearId): array
    {
        return StudentTimetableSubjectMapping::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->where('is_active', true)
            ->orderBy('json_subject')
            ->orderBy('tt_subject')
            ->get(['json_subject', 'tt_subject'])
            ->map(fn (StudentTimetableSubjectMapping $mapping): array => [
                'json_subject' => $this->cleanText($mapping->json_subject),
                'tt_subject' => $this->cleanText($mapping->tt_subject),
            ])
            ->filter(fn (array $mapping): bool => $mapping['json_subject'] !== '' && $mapping['tt_subject'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entryPayload(object $entry): ?array
    {
        $date = $this->dateFromValue($entry->date);
        $hour = $this->hourFromPeriod($entry->period);

        if (! $date || ! $hour) {
            return null;
        }

        $weekday = $date->dayOfWeekIso;
        if ($weekday > 6) {
            return null;
        }

        return [
            'date' => $date->toDateString(),
            'semester' => (int) $entry->semester,
            'weekday' => $weekday,
            'hour' => $hour,
            'subject' => $this->cleanText($entry->subject),
            'module_code' => $this->cleanText($entry->module_code),
            'teacher' => $this->cleanText($entry->teacher),
            'room' => $this->cleanText($entry->room),
            'class_name' => $this->cleanText($entry->class_name),
            'course' => $this->cleanText($entry->course),
            'student_group' => $this->cleanText($entry->student_group),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function groupKey(array $entry): string
    {
        return implode('|', [
            $entry['semester'],
            $entry['weekday'],
            $entry['hour'],
            mb_strtolower((string) $entry['course']),
            mb_strtolower((string) $entry['subject']),
            mb_strtolower((string) $entry['teacher']),
            mb_strtolower((string) $entry['class_name']),
            mb_strtolower((string) $entry['student_group']),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function groupPayload(Collection $entries, Schoolyear $schoolyear, array $subjectMappings): array
    {
        $firstEntry = $entries->first();
        $dates = $entries
            ->pluck('date')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $recurrence = $this->recurrenceForDates($dates);
        $isBlock = $this->isBlockCourse(
            (int) $firstEntry['semester'],
            $dates,
            $schoolyear,
        );

        return [
            'key' => md5($this->groupKey($firstEntry)),
            'semester' => (int) $firstEntry['semester'],
            'weekday' => (int) $firstEntry['weekday'],
            'hour' => (int) $firstEntry['hour'],
            'title' => $this->courseTitle($firstEntry, $subjectMappings),
            'display_label' => $this->displayLabel($firstEntry, $entries, $subjectMappings),
            'subject' => $firstEntry['subject'],
            'course' => $firstEntry['course'],
            'module_code' => $firstEntry['module_code'],
            'teacher' => $firstEntry['teacher'],
            'rooms' => $entries->pluck('room')->filter()->unique()->values()->all(),
            'class_name' => $firstEntry['class_name'],
            'student_group' => $firstEntry['student_group'],
            'first_date' => $dates[0] ?? null,
            'last_date' => $dates[count($dates) - 1] ?? null,
            'dates' => $dates,
            'dates_count' => count($dates),
            'recurrence_type' => $recurrence['type'],
            'recurrence_interval' => $recurrence['interval'],
            'recurrence_label' => $recurrence['label'],
            'is_block' => $isBlock,
            'block_label' => $isBlock ? 'Block' : null,
        ];
    }

    /**
     * @param  list<string>  $dates
     * @return array{type: string, interval: ?int, label: ?string}
     */
    private function recurrenceForDates(array $dates): array
    {
        if (count($dates) < 2) {
            return ['type' => 'single', 'interval' => null, 'label' => null];
        }

        $firstDate = CarbonImmutable::parse($dates[0]);
        $weekOffsets = collect($dates)
            ->map(fn (string $date): int => (int) round($firstDate->diffInDays(CarbonImmutable::parse($date)) / 7))
            ->unique()
            ->sort()
            ->values();

        $maxOffset = (int) $weekOffsets->max();
        $best = null;

        foreach ([1, 2, 3, 4] as $interval) {
            $expectedSlots = intdiv($maxOffset, $interval) + 1;
            $matchingOffsets = $weekOffsets->filter(fn (int $offset): bool => $offset % $interval === 0)->count();
            $coverage = $expectedSlots > 0 ? $matchingOffsets / $expectedSlots : 1.0;
            $alignment = $weekOffsets->isNotEmpty() ? $matchingOffsets / $weekOffsets->count() : 1.0;

            if ($coverage < 0.6 || $alignment < 0.8) {
                continue;
            }

            if (! $best || $coverage > $best['coverage'] || ($coverage === $best['coverage'] && $interval > $best['interval'])) {
                $best = [
                    'interval' => $interval,
                    'coverage' => $coverage,
                ];
            }
        }

        if (! $best) {
            return ['type' => 'irregular', 'interval' => null, 'label' => null];
        }

        $interval = (int) $best['interval'];

        return [
            'type' => $interval === 1 ? 'weekly' : "every_{$interval}_weeks",
            'interval' => $interval,
            'label' => "{$interval}-wöchig",
        ];
    }

    /**
     * @param  list<string>  $dates
     */
    private function isBlockCourse(int $semester, array $dates, Schoolyear $schoolyear): bool
    {
        if ($dates === []) {
            return false;
        }

        [$semesterFrom, $semesterUntil] = $this->semesterBounds($semester, $schoolyear);
        if (! $semesterFrom || ! $semesterUntil) {
            return false;
        }

        $firstDate = CarbonImmutable::parse($dates[0]);
        $lastDate = CarbonImmutable::parse($dates[count($dates) - 1]);

        return $firstDate->greaterThan($semesterFrom->addDays(self::BLOCK_EDGE_TOLERANCE_DAYS))
            || $lastDate->lessThan($semesterUntil->subDays(self::BLOCK_EDGE_TOLERANCE_DAYS));
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function semesterBounds(int $semester, Schoolyear $schoolyear): array
    {
        $schoolyearFrom = $this->dateFromValue($schoolyear->from);
        $schoolyearUntil = $this->dateFromValue($schoolyear->until);
        $semesterTwoStart = $this->dateFromValue($schoolyear->sem_2_start);

        if ($semester === 1) {
            return [
                $schoolyearFrom,
                $semesterTwoStart?->subDay(),
            ];
        }

        return [
            $semesterTwoStart,
            $schoolyearUntil,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function courseTitle(array $entry, array $subjectMappings): string
    {
        return $this->mappedDisplayCourseCode(
            (string) ($entry['course'] ?: $entry['subject']),
            $subjectMappings,
        ) ?: 'Ohne Bezeichnung';
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function displayLabel(array $entry, Collection $entries, array $subjectMappings): string
    {
        $rooms = $entries
            ->pluck('room')
            ->filter()
            ->map(fn (string $room): string => $this->withoutTimeFragments($room))
            ->filter()
            ->unique()
            ->values()
            ->implode(',');

        $primary = $this->mappedDisplayCourseCode(
            (string) ($entry['course'] ?: $entry['subject']),
            $subjectMappings,
        );
        $details = collect([
            $entry['class_name'],
            $entry['student_group'],
            $entry['teacher'],
            $rooms,
        ])
            ->filter()
            ->map(fn (string $segment): string => $this->withoutTimeFragments($segment));

        if ($primary !== '' && ! Str::startsWith((string) $details->first(), $primary)) {
            $details->prepend($primary);
        }

        $label = $this->withoutTimeFragments($details->implode(''));
        $label = $rooms !== ''
            ? $this->stripTrailingRoom($label, $rooms)
            : $this->stripRoomSuffix($label);

        return $this->formatDisplayLabel($label);
    }

    private function mappedDisplayCourseCode(string $value, array $subjectMappings): string
    {
        $courseCode = $this->cleanText($value);
        if ($courseCode === '') {
            return '';
        }

        $parts = $this->courseCodeParts($courseCode);
        foreach ($subjectMappings as $mapping) {
            if ($this->normalizedCourseCode($mapping['tt_subject']) !== $this->normalizedCourseCode($parts['base'])) {
                continue;
            }

            return $mapping['json_subject'].$parts['module'];
        }

        return $courseCode;
    }

    /**
     * @return array{base: string, module: string}
     */
    private function courseCodeParts(string $value): array
    {
        $courseCode = $this->cleanText($value);
        if (preg_match('/^([A-Za-zÄÖÜäöüß]+)(\d*)$/u', $courseCode, $match)) {
            return [
                'base' => $match[1],
                'module' => $match[2] ?? '',
            ];
        }

        return [
            'base' => $courseCode,
            'module' => '',
        ];
    }

    private function normalizedCourseCode(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/u', '')
            ->toString();
    }

    private function withoutTimeFragments(string $value): string
    {
        $cleaned = preg_replace('/\d{1,2}:\d{2}/', '', $value);

        return trim($cleaned ?: '');
    }

    private function stripTrailingRoom(string $value, string $rooms): string
    {
        return Str::endsWith($value, $rooms)
            ? Str::beforeLast($value, $rooms)
            : $value;
    }

    private function stripRoomSuffix(string $value): string
    {
        if (preg_match('/(\d+[A-Za-zÄÖÜäöüß]+(?:~\d+[A-Za-zÄÖÜäöüß]+)*|\d+)$/u', $value, $match)) {
            return Str::beforeLast($value, $match[1]);
        }

        return $value;
    }

    private function formatDisplayLabel(string $value): string
    {
        $formatted = preg_replace('/\s*-\s*/', ' - ', $value);

        return trim($formatted ?: $value);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isHiddenCourse(array $entry): bool
    {
        return mb_strtolower((string) ($entry['course'] ?? '')) === 'sprstd'
            || mb_strtolower((string) ($entry['subject'] ?? '')) === 'sprstd';
    }

    private function hourFromPeriod(?string $period): ?int
    {
        if (! preg_match('/\d+/', (string) $period, $match)) {
            return null;
        }

        return (int) $match[0];
    }

    private function dateFromValue(mixed $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function cleanText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function resolveSchoolyearIdForUser(User $authUser): int
    {
        $schoolyearId = $authUser->schoolyear_id
            ?? SchoolTool::query()
                ->where('school_id', $authUser->school_id)
                ->value('active_schoolyear_id');

        if (! $schoolyearId) {
            abort(422, 'Kein aktives Schuljahr gefunden.');
        }

        return (int) $schoolyearId;
    }
}
