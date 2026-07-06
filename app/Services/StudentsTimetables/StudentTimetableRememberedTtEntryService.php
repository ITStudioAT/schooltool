<?php

namespace App\Services\StudentsTimetables;

use App\Models\StudentTimetableRememberedTtEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudentTimetableRememberedTtEntryService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function offersForUser(User $authUser): array
    {
        return StudentTimetableRememberedTtEntry::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $this->schoolyearIdForUser($authUser))
            ->where('user_id', $authUser->id)
            ->orderBy('offer_name')
            ->orderBy('entry_date')
            ->orderBy('entry_time_from')
            ->get()
            ->groupBy('offer_key_hash')
            ->map(fn ($entries): array => $this->offerPayload($entries))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    public function updateForUser(User $authUser, array $offers): array
    {
        $schoolyearId = $this->schoolyearIdForUser($authUser);
        $normalizedOffers = $this->normalizedOffers($offers);

        DB::transaction(function () use ($authUser, $schoolyearId, $normalizedOffers): void {
            StudentTimetableRememberedTtEntry::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $schoolyearId)
                ->where('user_id', $authUser->id)
                ->delete();

            collect($normalizedOffers)->each(function (array $offer) use ($authUser, $schoolyearId): void {
                collect($offer['entries'])->each(function (array $entry) use ($authUser, $schoolyearId, $offer): void {
                    StudentTimetableRememberedTtEntry::query()->create([
                        'school_id' => $authUser->school_id,
                        'schoolyear_id' => $schoolyearId,
                        'user_id' => $authUser->id,
                        'offer_key_hash' => $this->hashKey($offer['key']),
                        'entry_key_hash' => $this->hashKey($entry['key']),
                        'offer_key' => $offer['key'],
                        'entry_key' => $entry['key'],
                        'offer_name' => $offer['name'],
                        'offer_schedule_label' => $this->nullableText($offer['scheduleLabel']),
                        'entry_date_label' => $this->nullableText($entry['dateLabel']),
                        'entry_date' => $this->dateValue($entry['dateValue']),
                        'entry_schedule_label' => $this->nullableText($entry['scheduleLabel']),
                        'entry_rooms_label' => $this->nullableText($entry['roomsLabel']),
                        'entry_time_from' => $this->timeValue($entry['timeFrom']),
                        'entry_time_until' => $this->timeValue($entry['timeUntil']),
                        'is_active' => $entry['active'] !== false,
                    ]);
                });
            });
        });

        return $this->offersForUser($authUser);
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<array<string, mixed>>
     */
    public function activeCourseGroupsForUser(User $authUser, array $courseGroups): array
    {
        $inactiveDatesByCourseGroupKey = $this->inactiveDatesByCourseGroupKey($authUser);

        if ($inactiveDatesByCourseGroupKey === []) {
            return $courseGroups;
        }

        return collect($courseGroups)
            ->map(fn (array $courseGroup): ?array => $this->courseGroupWithoutInactiveDates(
                $courseGroup,
                $inactiveDatesByCourseGroupKey,
            ))
            ->filter(fn (?array $courseGroup): bool => $courseGroup !== null)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $courseGroups
     * @return list<array<string, mixed>>
     */
    public function courseGroupsWithInactiveDatesForUser(User $authUser, array $courseGroups): array
    {
        $inactiveDatesByCourseGroupKey = $this->inactiveDatesByCourseGroupKey($authUser);

        if ($inactiveDatesByCourseGroupKey === []) {
            return $courseGroups;
        }

        return collect($courseGroups)
            ->map(fn (array $courseGroup): array => $this->courseGroupWithInactiveDates(
                $courseGroup,
                $inactiveDatesByCourseGroupKey,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, StudentTimetableRememberedTtEntry>  $entries
     * @return array<string, mixed>
     */
    private function offerPayload(iterable $entries): array
    {
        $entries = collect($entries)->values();
        $firstEntry = $entries->first();

        return [
            'key' => (string) $firstEntry->offer_key,
            'entries' => $entries
                ->map(fn (StudentTimetableRememberedTtEntry $entry): array => [
                    'key' => (string) $entry->entry_key,
                    'dateLabel' => (string) ($entry->entry_date_label ?: optional($entry->entry_date)->format('d.m.Y')),
                    'dateValue' => optional($entry->entry_date)->format('Y-m-d') ?: '',
                    'active' => (bool) $entry->is_active,
                    'roomsLabel' => (string) $entry->entry_rooms_label,
                    'scheduleLabel' => (string) $entry->entry_schedule_label,
                    'timeFrom' => $this->shortTime($entry->entry_time_from),
                    'timeUntil' => $this->shortTime($entry->entry_time_until),
                ])
                ->values()
                ->all(),
            'name' => (string) $firstEntry->offer_name,
            'scheduleLabel' => (string) $firstEntry->offer_schedule_label,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    private function normalizedOffers(array $offers): array
    {
        return collect($offers)
            ->map(function (array $offer): array {
                $entries = collect($offer['entries'] ?? [])
                    ->filter(fn (array $entry): bool => trim((string) ($entry['key'] ?? '')) !== '')
                    ->map(fn (array $entry): array => [
                        'key' => trim((string) $entry['key']),
                        'dateLabel' => trim((string) ($entry['dateLabel'] ?? '')),
                        'dateValue' => trim((string) ($entry['dateValue'] ?? '')),
                        'active' => ($entry['active'] ?? true) !== false,
                        'roomsLabel' => trim((string) ($entry['roomsLabel'] ?? '')),
                        'scheduleLabel' => trim((string) ($entry['scheduleLabel'] ?? '')),
                        'timeFrom' => trim((string) ($entry['timeFrom'] ?? '')),
                        'timeUntil' => trim((string) ($entry['timeUntil'] ?? '')),
                    ])
                    ->unique('key')
                    ->values()
                    ->all();

                return [
                    'key' => trim((string) ($offer['key'] ?? '')),
                    'entries' => $entries,
                    'name' => trim((string) ($offer['name'] ?? '')),
                    'scheduleLabel' => trim((string) ($offer['scheduleLabel'] ?? '')),
                ];
            })
            ->filter(fn (array $offer): bool => $offer['key'] !== '' && $offer['name'] !== '' && $offer['entries'] !== [])
            ->unique('key')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function inactiveDatesByCourseGroupKey(User $authUser): array
    {
        return StudentTimetableRememberedTtEntry::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $this->schoolyearIdForUser($authUser))
            ->where('is_active', false)
            ->whereNotNull('entry_date')
            ->get(['entry_key', 'entry_date'])
            ->reduce(function (array $inactiveDates, StudentTimetableRememberedTtEntry $entry): array {
                $date = $entry->entry_date?->format('Y-m-d');

                if (! $date) {
                    return $inactiveDates;
                }

                foreach ($this->courseGroupKeysFromEntryKey((string) $entry->entry_key, $date) as $courseGroupKey) {
                    $inactiveDates[$courseGroupKey] ??= [];
                    $inactiveDates[$courseGroupKey][$date] = true;
                }

                return $inactiveDates;
            }, []);
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, array<string, true>>  $inactiveDatesByCourseGroupKey
     * @return array<string, mixed>|null
     */
    private function courseGroupWithoutInactiveDates(array $courseGroup, array $inactiveDatesByCourseGroupKey): ?array
    {
        $courseGroupKey = trim((string) ($courseGroup['key'] ?? ''));
        $inactiveDates = $inactiveDatesByCourseGroupKey[$courseGroupKey] ?? [];

        if ($inactiveDates === []) {
            return $courseGroup;
        }

        $dates = $this->courseGroupDates($courseGroup);

        if ($dates === []) {
            return $courseGroup;
        }

        $activeDates = collect($dates)
            ->reject(fn (string $date): bool => isset($inactiveDates[$date]))
            ->values()
            ->all();

        if (count($activeDates) === count($dates)) {
            return $courseGroup;
        }

        if ($activeDates === []) {
            return null;
        }

        return [
            ...$courseGroup,
            'dates' => $activeDates,
            'dates_count' => count($activeDates),
            'first_date' => $activeDates[0],
            'last_date' => $activeDates[count($activeDates) - 1],
            'has_inactive_remembered_dates' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @param  array<string, array<string, true>>  $inactiveDatesByCourseGroupKey
     * @return array<string, mixed>
     */
    private function courseGroupWithInactiveDates(array $courseGroup, array $inactiveDatesByCourseGroupKey): array
    {
        $courseGroupKey = trim((string) ($courseGroup['key'] ?? ''));
        $inactiveDates = array_keys($inactiveDatesByCourseGroupKey[$courseGroupKey] ?? []);

        if ($inactiveDates === []) {
            return $courseGroup;
        }

        sort($inactiveDates);

        return [
            ...$courseGroup,
            'has_inactive_remembered_dates' => true,
            'inactive_dates' => $inactiveDates,
        ];
    }

    /**
     * @return list<string>
     */
    private function courseGroupKeysFromEntryKey(string $entryKey, string $date): array
    {
        $parts = collect(explode('|', $entryKey))
            ->map(fn (string $part): string => trim($part))
            ->values()
            ->all();

        $courseGroupKeys = [];

        foreach ($parts as $index => $part) {
            if (! $this->looksLikeCourseGroupKey($part)) {
                continue;
            }

            if (($parts[$index + 1] ?? '') !== $date) {
                continue;
            }

            $courseGroupKeys[] = $part;
        }

        if ($courseGroupKeys !== []) {
            return $this->uniqueStrings($courseGroupKeys);
        }

        return collect($parts)
            ->keys()
            ->filter(fn (int $index): bool => ($parts[$index] ?? '') === $date && $index > 0)
            ->map(function (int $dateIndex) use ($parts): string {
                $previousDateIndex = collect(array_slice($parts, 0, $dateIndex))
                    ->keys()
                    ->reverse()
                    ->first(fn (int $index): bool => $this->looksLikeDate($parts[$index] ?? ''));
                $startIndex = $previousDateIndex === null ? 0 : $previousDateIndex + 2;

                return implode('|', array_slice($parts, $startIndex, $dateIndex - $startIndex));
            })
            ->map(fn (string $courseGroupKey): string => trim($courseGroupKey, '| '))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function looksLikeCourseGroupKey(string $value): bool
    {
        return preg_match('/^[a-f0-9]{32}$/u', $value) === 1;
    }

    private function looksLikeDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/u', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $courseGroup
     * @return list<string>
     */
    private function courseGroupDates(array $courseGroup): array
    {
        if (! is_array($courseGroup['dates'] ?? null)) {
            return [];
        }

        return collect($courseGroup['dates'])
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function schoolyearIdForUser(User $authUser): int
    {
        if (! $authUser->school_id || ! $authUser->schoolyear_id) {
            abort(422, 'Bitte wÃ¤hlen Sie zuerst eine Schule und ein Schuljahr aus.');
        }

        return (int) $authUser->schoolyear_id;
    }

    private function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    private function nullableText(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function dateValue(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/u', $value) ? $value : null;
    }

    private function timeValue(string $value): ?string
    {
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/u', $value, $matches)) {
            return null;
        }

        return str_pad($matches[1], 2, '0', STR_PAD_LEFT).":{$matches[2]}:00";
    }

    private function shortTime(?string $value): string
    {
        return $value ? substr($value, 0, 5) : '';
    }
}
