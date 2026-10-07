<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Support\SchooltoolAssessmentJson;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class TeachingWorkJsonImport
{
    public function __construct(private SchooltoolAssessmentJson $parser, private TeachingCourseStudentEntryService $entries, private TeachingCourseWorkEntrySyncService $sync, private TeachingWorkMarkdownImport $markdown) {}

    /** @param list<UploadedFile> $uploads */
    public function parse(string $text, array $uploads): array
    {
        $package = $this->parser->parse($text);
        $references = array_values(array_filter([$package['overview_pdf'], ...array_column($package['records'], 'pdf')]));
        $this->parser->check(count($uploads) === count($references) && count($uploads) <= 20, 'Referenzierte PDF fehlt oder zusätzliche PDF ausgewählt.');
        $pdfs = [];
        foreach ($uploads as $upload) {
            $name = $upload->getClientOriginalName();
            $matches = array_values(array_filter($references, fn (array $pdf): bool => $pdf['filename'] === $name));
            $this->parser->check(count($matches) === 1 && ! isset($pdfs[$name]), 'PDF-Dateiname oder Zuordnung nicht eindeutig.');
            $stream = fopen($upload->getRealPath(), 'rb');
            try {
                $header = fread($stream, 5);
            } finally {
                fclose($stream);
            }
            $this->parser->check($header === '%PDF-' && $upload->getMimeType() === 'application/pdf' && hash_equals($matches[0]['sha256'], hash_file('sha256', $upload->getRealPath())), 'PDF-Inhalt oder Prüfsumme falsch: '.$name);
            $pdfs[$name] = ['name' => $name, 'sha256' => $matches[0]['sha256']];
        }
        $this->parser->check(strlen($text) + array_sum(array_map(fn (UploadedFile $file): int => $file->getSize(), $uploads)) <= 6 * 1024 * 1024, 'JSON-Paket überschreitet 6 MiB.');

        return ['package' => $package, 'pdfs' => $pdfs];
    }

    /** @return list<array{student_id: int, first_name: string, last_name: string, class_name: string}> */
    private function participants(TeachingCourse $course): array
    {
        $participants = [];
        foreach ($course->teachingCourseStudents()->whereNull('canceled_at')->with(['user', 'import116'])->get() as $student) {
            $user = $student->user;
            if (! $user || (int) $user->school_id !== (int) $course->school_id) {
                continue;
            }
            $import = $student->import116;
            $current = $import && (int) $import->school_id === (int) $course->school_id
                && (int) $import->schoolyear_id === (int) $course->schoolyear_id
                && ((int) $import->user_id === (int) $user->id || (int) $user->import116_id === (int) $import->id);
            $participants[] = ['student_id' => (int) $user->id, 'first_name' => (string) ($current ? $import->first_name : $user->first_name),
                'last_name' => (string) ($current ? $import->last_name : $user->last_name), 'class_name' => (string) ($current ? $import->class : $user->schoolclass)];
        }

        return $participants;
    }

    public function preview(TeachingCourseWork $work, User $actor, array $bundle): array
    {
        $package = $bundle['package'];
        $course = $work->teachingCourse;
        $definition = $this->entries->entryDefinitionsForCourse($actor, $course)->firstWhere('short_name', $work->type);
        if ($definition) {
            $definition = $definition->newQuery()->lockForUpdate()->find($definition->getKey());
        }
        $maximumMatches = false;
        if ($definition?->maximum_points !== null) {
            try {
                $maximumMatches = (string) BigDecimal::of((string) $definition->maximum_points)->multipliedBy(100)->toBigInteger() === (string) $package['maximum_minor'];
            } catch (MathException) {
                $maximumMatches = false;
            }
        }
        $this->parser->check($definition?->has_properties && $definition->properties_mode === 'points' && $definition->category === 'Benotung' && $maximumMatches, 'Zielarbeit benötigt Punkte-Bewertung mit identischem Maximum.');
        $participants = $this->participants($course);
        $groups = $this->sync->groupsForWork($work);
        $receipts = $work->status['assessment_json_imports'] ?? [];
        $rows = [];
        $targets = [];
        $blocked = false;
        foreach ($package['records'] as $record) {
            $identity = $record['identity'];
            $matches = array_values(array_filter($participants, function (array $person) use ($identity): bool {
                foreach (['first_name', 'last_name', 'class_name'] as $field) {
                    if (SchooltoolAssessmentJson::normalize($person[$field]) !== SchooltoolAssessmentJson::normalize($identity[$field])) {
                        return false;
                    }
                }

                return true;
            }));
            $ids = array_values(array_unique(array_column($matches, 'student_id')));
            $studentId = count($ids) === 1 ? $ids[0] : null;
            $groupIndexes = array_keys(array_filter($groups, fn (array $group): bool => $studentId !== null && in_array($studentId, $group['student_ids'] ?? [], true)));
            $index = count($groupIndexes) === 1 ? $groupIndexes[0] : null;
            $group = $groups[$index] ?? [];
            $groupName = $work->is_group_work ? (string) ($group['name'] ?? '') : (string) $course->title;
            $receipt = collect($receipts)->first(fn (array $item): bool => $item['exercise_id'] === $package['exercise_id'] && $item['participant_id'] === $record['participant_id']);
            $error = null;
            if ($studentId === null || count($groupIndexes) !== 1 || SchooltoolAssessmentJson::normalize($groupName) !== SchooltoolAssessmentJson::normalize($identity['group_name'])
                || ($identity['schooltool_person_id'] !== null && $identity['schooltool_person_id'] !== (string) $studentId)) {
                $error = 'Person, Klasse, Gruppe oder ID nicht eindeutig – Import gesperrt';
            } elseif (($receipt && $receipt['student_id'] !== $studentId) || in_array($studentId, $targets, true)) {
                $error = 'Teilnehmerkennung umgehängt oder Zielperson mehrfach – Import gesperrt';
            } elseif ($work->is_group_work && empty($work->groups[$index]['use_individual_grades'])) {
                $error = 'Gruppenarbeit benötigt Einzelbewertung – Import gesperrt';
            }
            $blocked = $blocked || $error !== null;
            $targets[] = $studentId;
            $unchanged = $receipt && $receipt['record_checksum'] === $record['record_checksum'];
            $status = $error ?? ($unchanged ? 'Unverändert' : ($record['evaluation_state'] === 'complete' ? ($receipt ? 'Aktualisierung' : 'Neu') : 'Bewertung erhalten'));
            $previous = [];
            foreach (['points' => 'points', 'grades' => 'grade', 'comments' => 'comment'] as $field => $key) {
                $previous[$field] = collect($group[$field] ?? [])->firstWhere('student_id', $studentId)[$key] ?? null;
            }
            $previous['grades'] ??= $group['grade'] ?? null;
            $previousReceipt = collect($receipts)->last(fn (array $item): bool => $item['student_id'] === $studentId);
            $rows[] = ['participant_id' => $record['participant_id'], 'identity' => $identity, 'student_id' => $studentId, 'group_index' => $index,
                'target' => $studentId ? ($matches[0] + ['group_name' => $groupName]) : null,
                'status' => $status, 'change' => $unchanged ? 'unchanged' : ($receipt ? 'updated' : 'new'),
                'submission_state' => $record['submission_state'], 'submission_note' => $record['submission_note'],
                'evaluation_state' => $record['evaluation_state'], 'evaluation_note' => $record['evaluation_note'],
                'event_times' => ['email_collected_at' => $record['email_collected_at'] ?? null, 'evaluation_completed_at' => $record['evaluation_completed_at'] ?? null],
                'previous_event_times' => ['email_collected_at' => $previousReceipt['record']['email_collected_at'] ?? null, 'evaluation_completed_at' => $previousReceipt['record']['evaluation_completed_at'] ?? null],
                'previous_evaluation_state' => $previousReceipt['record']['evaluation_state'] ?? null,
                'total_minor' => $record['total_minor'], 'comment' => $record['comment'], 'criteria' => $record['criteria'], 'adjustments' => $record['adjustments'],
                'previous' => $previous, 'previous_pdf' => collect($work->status['evaluation_pdfs'] ?? [])->firstWhere('student_id', $studentId),
                'pdf' => $record['pdf'], 'will_replace' => $error === null && ! $unchanged && $record['evaluation_state'] === 'complete'];
        }

        $submissionCheck = $package['submission_check'] ?? null;
        if ($submissionCheck !== null) {
            $this->parser->check(! $blocked && $this->expectedStudentIds($work) === $this->sortedIds($targets), 'Abgabeprüfung benötigt genau alle erwarteten Zielpersonen.');
            $this->checkDeadline($work, $submissionCheck);
            $previousCheck = collect($work->status['submission_checks'] ?? [])->last();
            if ($previousCheck) {
                $previousTime = $this->parser->instant($previousCheck['check']['checked_at']);
                $nextTime = $this->parser->instant($submissionCheck['checked_at']);
                $this->parser->check($nextTime > $previousTime || ($nextTime == $previousTime && $submissionCheck['check_checksum'] === $previousCheck['check']['check_checksum']), 'Abgabeprüfung ist älter oder widerspricht dem gespeicherten Prüfstand.');
                if ($nextTime == $previousTime) {
                    $this->parser->check($previousCheck['work_id'] === (int) $work->id && $previousCheck['course_id'] === (int) $course->id
                        && $previousCheck['roster'] === $this->rosterBinding($work), 'Geänderte Zielbasis benötigt einen neuen Prüfstand.');
                    $incoming = $receipts;
                    foreach ($package['records'] as $index => $record) {
                        $incoming = array_values(array_filter($incoming, fn (array $receipt): bool => $receipt['exercise_id'] !== $package['exercise_id'] || $receipt['participant_id'] !== $record['participant_id']));
                        $incoming[] = ['exercise_id' => $package['exercise_id'], 'participant_id' => $record['participant_id'], 'student_id' => $rows[$index]['student_id'], 'record' => $record];
                    }
                    $this->parser->check($previousCheck['bindings'] === $this->recordBindings($incoming), 'Geänderte Abgabefassung benötigt einen neuen Prüfstand.');
                }
            }
        }

        return ['target_work' => ['id' => $work->id, 'title' => $work->title, 'course_id' => $course->id, 'course_title' => $course->title],
            'submission_check' => $submissionCheck,
            'exercise' => $package['exercise'], 'exercise_id' => $package['exercise_id'], 'maximum_minor' => $package['maximum_minor'],
            'package_checksum' => $package['package_checksum'], 'overview_pdf' => $package['overview_pdf'], 'rows' => $rows, 'can_import' => ! $blocked,
            'previous_overview_pdf' => collect($work->status['evaluation_pdfs'] ?? [])->firstWhere('student_id', null),
            'overview_will_replace' => $package['overview_pdf'] !== null && ! collect($work->status['assessment_json_packages'] ?? [])->contains('package_checksum', $package['package_checksum']),
            'hash' => hash('sha256', json_encode([$work->getAttributes(), $course->getAttributes(), $participants, $groups, $definition->toArray(), $bundle], JSON_THROW_ON_ERROR))];
    }

    /** @param list<UploadedFile> $uploads
     * @param  list<string>  $createdPaths
     */
    public function apply(TeachingCourseWork $work, array $bundle, array $preview, array $uploads, array &$createdPaths): void
    {
        $package = $bundle['package'];
        $groups = $this->sync->groupsForWork($work);
        foreach ($groups as $index => &$group) {
            $group['use_individual_grades'] = (bool) ($work->groups[$index]['use_individual_grades'] ?? false);
        }
        unset($group);
        $pdfRows = [];
        $changed = false;
        foreach ($preview['rows'] as $row) {
            if (! $row['will_replace']) {
                continue;
            }
            $changed = true;
            $group = &$groups[$row['group_index']];
            $points = self::points($row['total_minor']);
            foreach (['points' => [$points, 'points'], 'grades' => [$points, 'grade'], 'comments' => [$row['comment'], 'comment']] as $field => [$value, $key]) {
                $group[$field] = array_values(array_filter($group[$field] ?? [], fn (array $item): bool => (int) $item['student_id'] !== $row['student_id']));
                $group[$field][] = ['student_id' => $row['student_id'], $key => $value];
            }
            unset($group);
            $pdfRows[] = ['student_id' => $row['student_id'], 'pdf' => $row['pdf'] ? $bundle['pdfs'][$row['pdf']['filename']] : null];
        }
        if ($changed) {
            $work->groups = $groups;
            $work->save();
            $this->sync->syncWork($work, useStoredGroups: true);
        }
        $repeatedPackage = collect($work->status['assessment_json_packages'] ?? [])->contains('package_checksum', $package['package_checksum']);
        $pdfPreview = ['pdf' => ! $repeatedPackage && $package['overview_pdf'] ? $bundle['pdfs'][$package['overview_pdf']['filename']] : null, 'rows' => $pdfRows];
        $pdfList = array_map(fn (UploadedFile $upload): array => $bundle['pdfs'][$upload->getClientOriginalName()], $uploads);
        $this->markdown->storePdfs($work, $pdfPreview, $uploads, $pdfList, $createdPaths);
        $status = $work->status ?? [];
        $receipts = $status['assessment_json_imports'] ?? [];
        foreach ($package['records'] as $index => $record) {
            if ($preview['rows'][$index]['change'] === 'unchanged') {
                continue;
            }
            $previous = collect($receipts)->first(fn (array $item): bool => $item['exercise_id'] === $package['exercise_id'] && $item['participant_id'] === $record['participant_id']);
            $history = $previous['history'] ?? [];
            if ($previous) {
                unset($previous['history']);
                $history[] = $previous;
            }
            $receipts = array_values(array_filter($receipts, fn (array $item): bool => $item['exercise_id'] !== $package['exercise_id'] || $item['participant_id'] !== $record['participant_id']));
            $receipts[] = ['exercise_id' => $package['exercise_id'], 'participant_id' => $record['participant_id'], 'student_id' => $preview['rows'][$index]['student_id'],
                'record_checksum' => $record['record_checksum'], 'record' => $record, 'history' => $history, 'imported_at' => now()->toISOString()];
        }
        $status['assessment_json_imports'] = $receipts;
        if (isset($package['submission_check'])) {
            $checks = $status['submission_checks'] ?? [];
            $bindings = $this->recordBindings($receipts);
            $last = collect($checks)->last();
            if (! $last || $last['check']['check_checksum'] !== $package['submission_check']['check_checksum'] || $last['bindings'] !== $bindings) {
                $checks[] = ['check' => $package['submission_check'], 'work_id' => (int) $work->id, 'course_id' => (int) $work->teaching_course_id,
                    'roster' => $this->rosterBinding($work), 'bindings' => $bindings, 'imported_at' => now()->toISOString()];
            }
            $status['submission_checks'] = $checks;
        }
        $packages = $status['assessment_json_packages'] ?? [];
        if (! collect($packages)->contains('package_checksum', $package['package_checksum'])) {
            $packages[] = ['exercise_id' => $package['exercise_id'], 'package_checksum' => $package['package_checksum'], 'exercise' => $package['exercise'], 'imported_at' => now()->toISOString()];
            $status['folder_imported_at'] = now()->toISOString();
        }
        $status['assessment_json_packages'] = $packages;
        if ($work->status !== $status) {
            $work->status = $status;
            $work->save();
        }
    }

    public static function points(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @return list<int> */
    private function sortedIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /** @return list<int> */
    private function expectedStudentIds(TeachingCourseWork $work): array
    {
        $ids = array_column($this->participants($work->teachingCourse), 'student_id');
        if ($work->is_group_work) {
            $assigned = array_merge([], ...array_column($this->sync->groupsForWork($work), 'student_ids'));
            $ids = array_values(array_intersect($ids, $assigned));
        }

        return $this->sortedIds($ids);
    }

    private function rosterBinding(TeachingCourseWork $work): string
    {
        $ids = $this->expectedStudentIds($work);
        $people = array_values(array_filter($this->participants($work->teachingCourse), fn (array $person): bool => in_array($person['student_id'], $ids, true)));
        usort($people, fn (array $left, array $right): int => $left['student_id'] <=> $right['student_id']);
        $groups = array_map(fn (array $group): array => ['name' => $group['name'] ?? '', 'student_ids' => $this->sortedIds($group['student_ids'] ?? [])], $this->sync->groupsForWork($work));

        return SchooltoolAssessmentJson::digest([$people, (string) $work->teachingCourse->title, (bool) $work->is_group_work, $groups]);
    }

    /** @return array<int, string> */
    private function recordBindings(array $receipts): array
    {
        $bindings = [];
        foreach ($receipts as $receipt) {
            $record = $receipt['record'];
            $bindings[(int) $receipt['student_id']] = SchooltoolAssessmentJson::digest([$receipt['exercise_id'], $receipt['participant_id'], $record['identity'] ?? [],
                $record['submission_state'] ?? null, $record['source_fingerprint'] ?? null, $record['email_collected_at'] ?? null]);
        }
        ksort($bindings, SORT_NUMERIC);

        return $bindings;
    }

    private function checkDeadline(TeachingCourseWork $work, array $check): void
    {
        $date = $work->finish_until_date?->format('Y-m-d') ?? '';
        $time = (string) $work->finish_until_time;
        $this->parser->check($date !== '' && preg_match('/\A[0-9]{2}:[0-9]{2}\z/', $time) === 1, 'Abgabeprüfung benötigt eine belegte Ziel-Frist mit Uhrzeit.');
        $instant = $this->parser->instant($check['deadline_at']);
        $zone = new DateTimeZone('Europe/Vienna');
        $wallTime = $date.' '.$time.':00';
        $this->parser->check($instant->setTimezone($zone)->format('Y-m-d H:i:s.u') === $wallTime.'.000000', 'Abgabeprüfung widerspricht der aktuellen Frist.');
        $wall = new DateTimeImmutable($wallTime, new DateTimeZone('UTC'));
        $offsets = array_unique(array_column($zone->getTransitions($wall->getTimestamp() - 86400, $wall->getTimestamp() + 86400), 'offset'));
        $matches = array_filter($offsets, fn (int $offset): bool => (new DateTimeImmutable('@'.($wall->getTimestamp() - $offset)))->setTimezone($zone)->format('Y-m-d H:i:s') === $wallTime);
        $this->parser->check(count($matches) === 1, 'Mehrdeutige Ziel-Frist muss vor dem Import geklärt werden.');
    }

    /** @return array<string, mixed> */
    public function submissionCheckStatus(TeachingCourseWork $work): array
    {
        $unknown = ['complete' => false, 'checked_at' => null];
        $receipt = collect($work->status['submission_checks'] ?? [])->last();
        if (! $receipt) {
            return $unknown;
        }
        try {
            $check = $receipt['check'];
            $imports = $work->status['assessment_json_imports'] ?? [];
            $this->parser->check($receipt['work_id'] === (int) $work->id && $receipt['course_id'] === (int) $work->teaching_course_id
                && $receipt['roster'] === $this->rosterBinding($work) && $receipt['bindings'] === $this->recordBindings($imports), 'Abgabeprüfung: Zielbasis geändert.');
            $records = array_column(array_values(array_filter($imports, fn (array $item): bool => $item['exercise_id'] === $check['exercise_id'])), 'record');
            $this->parser->submissionCheck($check, $check['exercise_id'], $records);
            $this->checkDeadline($work, $check);

            return ['complete' => $check['state'] === 'complete', 'checked_at' => $check['checked_at'], 'student_ids' => $this->expectedStudentIds($work),
                'deadline_date' => $work->finish_until_date->format('Y-m-d'), 'deadline_time' => $work->finish_until_time];
        } catch (ValidationException) {
            return $unknown;
        }
    }
}
