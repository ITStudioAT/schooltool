<?php

namespace App\Services\StudentsTimetables;

use InvalidArgumentException;

class StudentTimetableV3TimetableStorage
{
    private const STORAGE_VERSION = 1;

    /**
     * @param  list<array<string, mixed>>  $timetables
     * @return array{storage_version: int, lessons: list<array<string, mixed>>, timetables: list<array<string, mixed>>}
     */
    public function compact(array $timetables): array
    {
        $lessons = [];
        $lessonIdsByPayload = [];
        $compactTimetables = [];

        foreach ($timetables as $timetable) {
            $compactTimetable = $timetable;
            $compactTimetable['slots'] = [];
            $slots = $timetable['slots'] ?? [];

            if (! is_array($slots)) {
                throw new InvalidArgumentException('Timetable slots must be an array.');
            }

            foreach ($slots as $slotKey => $slot) {
                if (! is_array($slot)) {
                    throw new InvalidArgumentException('Every timetable slot must be an array.');
                }

                $primaryLesson = $slot;
                unset($primaryLesson['sameSlotEntries'], $primaryLesson['conflicts']);

                $compactSlot = [
                    'lesson_id' => $this->lessonId($primaryLesson, $lessons, $lessonIdsByPayload),
                    'conflict_lesson_ids' => $this->lessonIds(
                        $slot['conflicts'] ?? [],
                        $lessons,
                        $lessonIdsByPayload,
                    ),
                ];

                if (array_key_exists('sameSlotEntries', $slot)) {
                    $compactSlot['same_slot_lesson_ids'] = $this->lessonIds(
                        $slot['sameSlotEntries'],
                        $lessons,
                        $lessonIdsByPayload,
                    );
                }

                $compactTimetable['slots'][(string) $slotKey] = $compactSlot;
            }

            $compactTimetables[] = $compactTimetable;
        }

        return [
            'storage_version' => self::STORAGE_VERSION,
            'lessons' => $lessons,
            'timetables' => $compactTimetables,
        ];
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $storedTimetables
     * @return list<array<string, mixed>>|null
     */
    public function expand(array $storedTimetables): ?array
    {
        if (array_is_list($storedTimetables)) {
            return $this->isListOfArrays($storedTimetables) ? $storedTimetables : null;
        }

        if (($storedTimetables['storage_version'] ?? null) !== self::STORAGE_VERSION) {
            return null;
        }

        $lessons = $storedTimetables['lessons'] ?? null;
        $compactTimetables = $storedTimetables['timetables'] ?? null;

        if (! is_array($lessons) || ! array_is_list($lessons) || ! $this->isListOfArrays($lessons)) {
            return null;
        }

        if (! is_array($compactTimetables) || ! array_is_list($compactTimetables) || ! $this->isListOfArrays($compactTimetables)) {
            return null;
        }

        $timetables = [];

        foreach ($compactTimetables as $compactTimetable) {
            $compactSlots = $compactTimetable['slots'] ?? null;

            if (! is_array($compactSlots)) {
                return null;
            }

            $timetable = $compactTimetable;
            $timetable['slots'] = [];

            foreach ($compactSlots as $slotKey => $compactSlot) {
                if (! is_array($compactSlot)) {
                    return null;
                }

                $lesson = $this->lesson($lessons, $compactSlot['lesson_id'] ?? null);
                $conflicts = $this->lessons($lessons, $compactSlot['conflict_lesson_ids'] ?? null);

                if ($lesson === null || $conflicts === null) {
                    return null;
                }

                $lesson['conflicts'] = $conflicts;

                if (array_key_exists('same_slot_lesson_ids', $compactSlot)) {
                    $sameSlotEntries = $this->lessons($lessons, $compactSlot['same_slot_lesson_ids']);

                    if ($sameSlotEntries === null) {
                        return null;
                    }

                    $lesson['sameSlotEntries'] = $sameSlotEntries;
                }

                $timetable['slots'][(string) $slotKey] = $lesson;
            }

            $timetables[] = $timetable;
        }

        return $timetables;
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $storedTimetables
     * @return array{items: list<array<string, mixed>>, total: int}|null
     */
    public function expandPage(array $storedTimetables, int $page, int $perPage): ?array
    {
        if ($page < 1 || $perPage < 1 || $page > intdiv(PHP_INT_MAX, $perPage)) {
            throw new InvalidArgumentException('Timetable pagination values must be positive integers.');
        }

        $offset = ($page - 1) * $perPage;

        if (array_is_list($storedTimetables)) {
            if (! $this->isListOfArrays($storedTimetables)) {
                return null;
            }

            return [
                'items' => array_values(array_slice($storedTimetables, $offset, $perPage)),
                'total' => count($storedTimetables),
            ];
        }

        if (($storedTimetables['storage_version'] ?? null) !== self::STORAGE_VERSION) {
            return null;
        }

        $lessons = $storedTimetables['lessons'] ?? null;
        $compactTimetables = $storedTimetables['timetables'] ?? null;

        if (! is_array($lessons) || ! array_is_list($lessons) || ! $this->isListOfArrays($lessons)) {
            return null;
        }

        if (
            ! is_array($compactTimetables)
            || ! array_is_list($compactTimetables)
            || ! $this->compactTimetablesAreValid($compactTimetables, $lessons)
        ) {
            return null;
        }

        $pageEnvelope = $storedTimetables;
        $pageEnvelope['timetables'] = array_values(array_slice($compactTimetables, $offset, $perPage));
        $expandedTimetables = $this->expand($pageEnvelope);

        if ($expandedTimetables === null) {
            return null;
        }

        return [
            'items' => $expandedTimetables,
            'total' => count($compactTimetables),
        ];
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $storedTimetables
     * @param  callable(array<string, mixed>): bool  $matches
     * @return array{items: list<array<string, mixed>>, total: int, unfiltered_total: int}|null
     */
    public function expandFilteredPage(
        array $storedTimetables,
        int $page,
        int $perPage,
        callable $matches,
    ): ?array {
        if ($page < 1 || $perPage < 1 || $page > intdiv(PHP_INT_MAX, $perPage)) {
            throw new InvalidArgumentException('Timetable pagination values must be positive integers.');
        }

        $offset = ($page - 1) * $perPage;

        if (array_is_list($storedTimetables)) {
            if (! $this->isListOfArrays($storedTimetables)) {
                return null;
            }

            $filteredPage = $this->filteredPageItems($storedTimetables, $offset, $perPage, $matches);

            return [
                ...$filteredPage,
                'unfiltered_total' => count($storedTimetables),
            ];
        }

        if (($storedTimetables['storage_version'] ?? null) !== self::STORAGE_VERSION) {
            return null;
        }

        $lessons = $storedTimetables['lessons'] ?? null;
        $compactTimetables = $storedTimetables['timetables'] ?? null;

        if (! is_array($lessons) || ! array_is_list($lessons) || ! $this->isListOfArrays($lessons)) {
            return null;
        }

        if (
            ! is_array($compactTimetables)
            || ! array_is_list($compactTimetables)
            || ! $this->compactTimetablesAreValid($compactTimetables, $lessons)
        ) {
            return null;
        }

        $filteredPage = $this->filteredPageItems($compactTimetables, $offset, $perPage, $matches);
        $pageEnvelope = $storedTimetables;
        $pageEnvelope['timetables'] = $filteredPage['items'];
        $expandedTimetables = $this->expand($pageEnvelope);

        if ($expandedTimetables === null) {
            return null;
        }

        return [
            'items' => $expandedTimetables,
            'total' => $filteredPage['total'],
            'unfiltered_total' => count($compactTimetables),
        ];
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $storedTimetables
     * @param  array<string, callable(array<string, mixed>): bool>  $matchers
     * @return array<string, int>|null
     */
    public function countMatches(array $storedTimetables, array $matchers): ?array
    {
        if (array_is_list($storedTimetables)) {
            if (! $this->isListOfArrays($storedTimetables)) {
                return null;
            }

            return $this->matchingCounts($storedTimetables, $matchers);
        }

        if (($storedTimetables['storage_version'] ?? null) !== self::STORAGE_VERSION) {
            return null;
        }

        $lessons = $storedTimetables['lessons'] ?? null;
        $compactTimetables = $storedTimetables['timetables'] ?? null;

        if (! is_array($lessons) || ! array_is_list($lessons) || ! $this->isListOfArrays($lessons)) {
            return null;
        }

        if (
            ! is_array($compactTimetables)
            || ! array_is_list($compactTimetables)
            || ! $this->compactTimetablesAreValid($compactTimetables, $lessons)
        ) {
            return null;
        }

        return $this->matchingCounts($compactTimetables, $matchers);
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $storedTimetables */
    public function isCompact(array $storedTimetables): bool
    {
        return ! array_is_list($storedTimetables)
            && ($storedTimetables['storage_version'] ?? null) === self::STORAGE_VERSION;
    }

    /**
     * @param  list<array<string, mixed>>  $timetables
     * @param  callable(array<string, mixed>): bool  $matches
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function filteredPageItems(
        array $timetables,
        int $offset,
        int $perPage,
        callable $matches,
    ): array {
        $items = [];
        $total = 0;

        foreach ($timetables as $timetable) {
            if (! $matches($timetable)) {
                continue;
            }

            if ($total >= $offset && count($items) < $perPage) {
                $items[] = $timetable;
            }

            $total++;
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $timetables
     * @param  array<string, callable(array<string, mixed>): bool>  $matchers
     * @return array<string, int>
     */
    private function matchingCounts(array $timetables, array $matchers): array
    {
        $counts = array_fill_keys(array_keys($matchers), 0);

        foreach ($timetables as $timetable) {
            foreach ($matchers as $matcherKey => $matcher) {
                if ($matcher($timetable)) {
                    $counts[$matcherKey]++;
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @param  list<array<string, mixed>>  $lessons
     * @param  array<string, int>  $lessonIdsByPayload
     */
    private function lessonId(array $lesson, array &$lessons, array &$lessonIdsByPayload): int
    {
        $payload = json_encode($lesson, JSON_THROW_ON_ERROR);
        $payloadHash = hash('sha256', $payload);

        if (array_key_exists($payloadHash, $lessonIdsByPayload)) {
            return $lessonIdsByPayload[$payloadHash];
        }

        $lessonId = count($lessons);
        $lessons[] = $lesson;
        $lessonIdsByPayload[$payloadHash] = $lessonId;

        return $lessonId;
    }

    /**
     * @param  list<array<string, mixed>>  $lessons
     * @param  array<string, int>  $lessonIdsByPayload
     * @return list<int>
     */
    private function lessonIds(mixed $lessonValues, array &$lessons, array &$lessonIdsByPayload): array
    {
        if (! is_array($lessonValues)) {
            throw new InvalidArgumentException('Timetable lessons must be an array.');
        }

        $lessonIds = [];

        foreach ($lessonValues as $lesson) {
            if (! is_array($lesson)) {
                throw new InvalidArgumentException('Every timetable lesson must be an array.');
            }

            $lessonIds[] = $this->lessonId($lesson, $lessons, $lessonIdsByPayload);
        }

        return $lessonIds;
    }

    /**
     * @param  list<array<string, mixed>>  $lessons
     * @return list<array<string, mixed>>|null
     */
    private function lessons(array $lessons, mixed $lessonIds): ?array
    {
        if (! is_array($lessonIds) || ! array_is_list($lessonIds)) {
            return null;
        }

        $resolvedLessons = [];

        foreach ($lessonIds as $lessonId) {
            $lesson = $this->lesson($lessons, $lessonId);

            if ($lesson === null) {
                return null;
            }

            $resolvedLessons[] = $lesson;
        }

        return $resolvedLessons;
    }

    /**
     * @param  list<array<string, mixed>>  $lessons
     * @return array<string, mixed>|null
     */
    private function lesson(array $lessons, mixed $lessonId): ?array
    {
        if (! is_int($lessonId) || ! array_key_exists($lessonId, $lessons)) {
            return null;
        }

        return $lessons[$lessonId];
    }

    /**
     * @param  list<array<string, mixed>>  $compactTimetables
     * @param  list<array<string, mixed>>  $lessons
     */
    private function compactTimetablesAreValid(array $compactTimetables, array $lessons): bool
    {
        foreach ($compactTimetables as $compactTimetable) {
            $compactSlots = $compactTimetable['slots'] ?? null;

            if (! is_array($compactSlots)) {
                return false;
            }

            foreach ($compactSlots as $compactSlot) {
                if (! is_array($compactSlot)) {
                    return false;
                }

                if ($this->lesson($lessons, $compactSlot['lesson_id'] ?? null) === null) {
                    return false;
                }

                if (! $this->lessonIdsAreValid($lessons, $compactSlot['conflict_lesson_ids'] ?? null)) {
                    return false;
                }

                if (
                    array_key_exists('same_slot_lesson_ids', $compactSlot)
                    && ! $this->lessonIdsAreValid($lessons, $compactSlot['same_slot_lesson_ids'])
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param list<array<string, mixed>> $lessons */
    private function lessonIdsAreValid(array $lessons, mixed $lessonIds): bool
    {
        if (! is_array($lessonIds) || ! array_is_list($lessonIds)) {
            return false;
        }

        foreach ($lessonIds as $lessonId) {
            if ($this->lesson($lessons, $lessonId) === null) {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed> $values */
    private function isListOfArrays(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_array($value)) {
                return false;
            }
        }

        return true;
    }
}
