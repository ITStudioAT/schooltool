<?php

namespace App\Services\StudentsTimetables;

use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TimetableImport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TimetableImportComparisonService
{
    private const CONTENT_COLUMNS = [
        'identity_hash', 'date', 'semester', 'source_identifier', 'period',
        'starts_at', 'ends_at', 'subject', 'class_name', 'course', 'module_code',
    ];

    /** @return list<array{key: string, label: string, from: string, until: string}> */
    public function replacementScopes(Schoolyear $schoolyear): array
    {
        if (! $schoolyear->from || ! $schoolyear->until || ! $schoolyear->sem_2_start) {
            return [];
        }

        $from = CarbonImmutable::parse($schoolyear->from)->toDateString();
        $until = CarbonImmutable::parse($schoolyear->until)->toDateString();
        $secondSemester = CarbonImmutable::parse($schoolyear->sem_2_start)->toDateString();

        if ($from >= $secondSemester || $secondSemester > $until) {
            return [];
        }

        return [
            ['key' => 'semester1', 'label' => '1. Semester', 'from' => $from, 'until' => CarbonImmutable::parse($secondSemester)->subDay()->toDateString()],
            ['key' => 'semester2', 'label' => '2. Semester', 'from' => $secondSemester, 'until' => $until],
            ['key' => 'schoolyear', 'label' => 'Ganzes Schuljahr', 'from' => $from, 'until' => $until],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $analysis
     * @param  array{key: string, label: string, from: string, until: string}|null  $scope
     * @return array<string, mixed>
     */
    public function compare(TimetableImport $import, array $rows, array $analysis, string $operation, ?array $scope): array
    {
        $incoming = collect($rows)->keyBy('identity_hash')->sortKeys();
        $current = StudentTimetableEntry::current()
            ->where('school_id', $import->school_id)
            ->where('schoolyear_id', $import->schoolyear_id)
            ->when($scope, fn ($query) => $query->whereBetween('date', [$scope['from'], $scope['until']]))
            ->toBase()
            ->get([...self::CONTENT_COLUMNS, 'is_active'])
            ->map(fn (object $row): array => (array) $row)
            ->keyBy('identity_hash')
            ->sortKeys();

        $new = 0;
        $updated = 0;
        $unchanged = 0;
        foreach ($incoming as $identity => $row) {
            if (! $existing = $current->get($identity)) {
                $new++;

                continue;
            }

            if ($this->content($row) !== $this->content($existing)) {
                $updated++;

                continue;
            }

            $unchanged++;
        }

        $removed = $operation === 'replace' && $scope
            ? $current->diffKeys($incoming)
            : collect();
        $appointments = $this->appointments($removed);
        $outOfScope = $scope && $incoming->contains(fn (array $row): bool => $row['date'] < $scope['from'] || $row['date'] > $scope['until']);
        $invalid = (int) ($analysis['tt_skipped_invalid'] ?? 0);
        $canConfirm = $incoming->isNotEmpty() && ! $outOfScope && ($operation === 'merge' || $scope);
        $message = 'Die Änderungen können übernommen werden.';

        if ($incoming->isEmpty()) {
            $message = 'Die Datei enthält keine verwendbaren Unterrichtseinträge.';
        } elseif ($outOfScope) {
            $message = 'Die Datei enthält Termine außerhalb des gewählten Zeitraums. Bitte wählen Sie einen passenden Zeitraum.';
        } elseif ($operation === 'replace' && ! $scope) {
            $message = 'Bitte wählen Sie den Zeitraum, den diese Datei vollständig ersetzt.';
        } elseif ($invalid > 0) {
            $message = "{$invalid} fehlerhafte TT-Einträge werden übersprungen. Die Änderungen berücksichtigen nur gültige Unterrichtseinträge.";
        }

        return [
            'operation' => $operation,
            'scope' => $scope,
            'can_confirm' => (bool) $canConfirm,
            'message' => $message,
            'new_entries' => $new,
            'updated_entries' => $updated,
            'unchanged_entries' => $unchanged,
            'removed_entries' => $removed->count(),
            'removed_appointments' => $appointments,
            'removed_appointment_count' => count($appointments),
            'invalid_entries' => $invalid,
            'fingerprint' => hash('sha256', json_encode([
                'school_id' => $import->school_id,
                'schoolyear_id' => $import->schoolyear_id,
                'operation' => $operation,
                'scope' => $scope,
                'source' => is_file(storage_path($import->file_path)) ? hash_file('sha256', storage_path($import->file_path)) : null,
                'incoming' => $incoming->map(fn (array $row): array => $this->content($row))->all(),
                'current' => $current->map(fn (array $row): array => [...$this->content($row), 'is_active' => (bool) $row['is_active']])->all(),
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    /** @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function content(array $row): array
    {
        $content = [];
        foreach (self::CONTENT_COLUMNS as $column) {
            $content[$column] = (string) ($row[$column] ?? '');
        }

        return $content;
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $entries
     * @return list<array{course: string, date: string, weekday: string, starts_at: string, ends_at: string, entry_count: int}>
     */
    private function appointments(Collection $entries): array
    {
        $appointments = [];
        $weekdays = [1 => 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $groups = $entries->groupBy(fn (array $entry): string => $entry['class_name'].'|'.$entry['date'])->sortKeys();

        foreach ($groups as $group) {
            $lastPeriod = null;
            foreach ($group->sortBy([['starts_at', 'asc'], ['period', 'asc'], ['ends_at', 'asc']]) as $entry) {
                $course = (string) $entry['class_name'];
                $date = (string) $entry['date'];
                $from = substr((string) $entry['starts_at'], 0, 5);
                $until = substr((string) $entry['ends_at'], 0, 5);
                preg_match('/\d+/', (string) $entry['period'], $periodMatch);
                $period = isset($periodMatch[0]) ? (int) $periodMatch[0] : null;
                $lastIndex = array_key_last($appointments);
                $last = $lastIndex !== null ? $appointments[$lastIndex] : null;

                if ($last && $last['course'] === $course && $last['date'] === $date
                    && (($last['starts_at'] === $from && $last['ends_at'] === $until)
                        || ($from !== '' && $last['ends_at'] === $from && $period !== null && $lastPeriod !== null && $period === $lastPeriod + 1))) {
                    $appointments[$lastIndex]['ends_at'] = $until;
                    $appointments[$lastIndex]['entry_count']++;
                    $lastPeriod = $period;

                    continue;
                }

                $appointments[] = [
                    'course' => $course,
                    'date' => $date,
                    'weekday' => $weekdays[CarbonImmutable::parse($date)->dayOfWeekIso],
                    'starts_at' => $from,
                    'ends_at' => $until,
                    'entry_count' => 1,
                ];
                $lastPeriod = $period;
            }
        }

        return $appointments;
    }
}
