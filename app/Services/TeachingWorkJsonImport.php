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
use JsonException;

class TeachingWorkJsonImport
{
    private const LEGACY_BINDING = 'source_fingerprint';

    private const SUBMISSION_BINDING = 'submission_sha256';

    public function __construct(private SchooltoolAssessmentJson $parser, private TeachingCourseStudentEntryService $entries, private TeachingCourseWorkEntrySyncService $sync, private TeachingWorkMarkdownImport $markdown, private TeachingWorkAiExport $identityExport) {}

    /** @param list<UploadedFile> $uploads */
    public function parse(string $text, array $uploads, ?string $identityExportText = null): array
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
        $this->parser->check(strlen($text) + strlen($identityExportText ?? '') + array_sum(array_map(fn (UploadedFile $file): int => $file->getSize(), $uploads)) <= 6 * 1024 * 1024, 'JSON-Paket überschreitet 6 MiB.');

        $bundle = ['package' => $package, 'pdfs' => $pdfs];
        if ($identityExportText !== null) {
            $this->parser->check(strlen($identityExportText) <= 1048576, 'Arbeitsdatenexport überschreitet 1 MiB.');
            try {
                $source = json_decode(str_starts_with($identityExportText, "\xEF\xBB\xBF") ? substr($identityExportText, 3) : $identityExportText, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['identity_export' => 'Arbeitsdatenexport ist kein gültiges UTF-8-JSON.']);
            }
            $this->parser->check(is_array($source) && ($source['schema_version'] ?? null) === 1 && ($source['kind'] ?? null) === 'schooltool.ai-work-export', 'Ungültiger ursprünglicher Arbeitsdatenexport.');
            $bundle['identity_export'] = ['source' => $source, 'sha256' => hash('sha256', $identityExportText)];
        }

        return $bundle;
    }

    /** @return list<array{student_id: int, first_name: string, last_name: string, class_name: string}> */
    public function participants(TeachingCourse $course): array
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

    /** @param list<array<string, mixed>> $availabilitySources */
    public function preview(TeachingCourseWork $work, User $actor, array $bundle, array $availabilitySources = []): array
    {
        $package = $bundle['package'];
        $course = $work->teachingCourse;
        $identityBinding = isset($bundle['identity_export']) ? $this->identityBinding($work, $actor, $bundle) : null;
        $deadlineContext = $this->deadlineContext($work, $package);
        $deadlineNeedsReview = $deadlineContext['requires_review'] ?? false;
        if (isset($package['teacher_absence_decision'])) {
            if (! $deadlineNeedsReview) {
                $this->checkDeadline($work, $package['teacher_absence_decision']);
            }
        }
        $previousDownload = null;
        if (isset($package['final_download'])) {
            if (! $deadlineNeedsReview) {
                $this->checkDeadline($work, $package['final_download']);
            }
            $previousPackage = collect($work->status['assessment_json_packages'] ?? [])->last(fn (array $item): bool => $item['exercise_id'] === $package['exercise_id'] && isset($item['final_download']));
            if ($previousPackage) {
                $previousDownload = $previousPackage['final_download'];
                $previousTime = $this->parser->instant($previousDownload['download_completed_at']);
                $nextTime = $this->parser->instant($package['final_download']['download_completed_at']);
                $this->parser->check($nextTime > $previousTime || ($nextTime == $previousTime && $previousDownload['download_checksum'] === $package['final_download']['download_checksum']), 'Abschlussdownload ist älter oder widerspricht dem gespeicherten Bestand.');
            }
        }
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
                || ($identity['schooltool_person_id'] !== null && ($identityBinding['student_ids'][$identity['schooltool_person_id']] ?? $identity['schooltool_person_id']) !== (string) $studentId)) {
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
                'pdf' => $record['pdf'], 'deadline_requires_review' => $deadlineNeedsReview,
                'will_replace' => ! $deadlineNeedsReview && $error === null && ! $unchanged && $record['evaluation_state'] === 'complete'];
        }

        if (isset($package['final_download'])) {
            $this->parser->check(! $blocked && $this->expectedStudentIds($work) === $this->sortedIds($targets), 'Abschlussdownload benötigt genau alle erwarteten Zielpersonen.');
        }
        $submissionCheck = $package['submission_check'] ?? null;
        if ($submissionCheck !== null) {
            $this->parser->check(! $blocked && $this->expectedStudentIds($work) === $this->sortedIds($targets), 'Abgabeprüfung benötigt genau alle erwarteten Zielpersonen.');
            if ($submissionCheck['schema_version'] === 2) {
                foreach ($submissionCheck['participants'] as $person) {
                    $row = collect($rows)->firstWhere('participant_id', $person['participant_id']);
                    $sources = array_values(array_filter($availabilitySources, fn (array $source): bool => (int) $source['student_id'] === (int) $row['student_id']
                        && in_array($source['log_sha256'], $person['availability_evidence_sha256'], true)
                        && (! isset($source['exercise_id']) || ($source['exercise_id'] === $package['exercise_id'] && $source['participant_id'] === $person['participant_id']))));
                    $this->parser->check($sources !== [], 'Abgabeprüfung: personenbezogener Bereitstellungs- oder echter Aufgabenversandbeleg fehlt.');
                    $earliest = min(array_map(fn (array $source): DateTimeImmutable => $this->parser->instant($source['available_at']), $sources));
                    $this->parser->check($this->parser->instant($person['available_at']) == $earliest
                        && collect($sources)->contains(fn (array $source): bool => $source['method'] === $person['availability_method'] && $this->parser->instant($source['available_at']) == $earliest),
                        'Abgabeprüfung: Verfügbarkeitsbeginn oder Ausgabeweg widerspricht dem gebundenen Beleg.');
                }
            }
            if (! $deadlineNeedsReview) {
                $this->checkDeadline($work, $submissionCheck);
            }
            $previousCheck = collect($work->status['submission_checks'] ?? [])->last();
            if ($previousCheck) {
                $previousTime = $this->parser->instant($previousCheck['check']['checked_at']);
                $nextTime = $this->parser->instant($submissionCheck['checked_at']);
                $this->parser->check($nextTime > $previousTime || ($nextTime == $previousTime && $submissionCheck['check_checksum'] === $previousCheck['check']['check_checksum']), 'Abgabeprüfung ist älter oder widerspricht dem gespeicherten Prüfstand.');
                if ($nextTime == $previousTime) {
                    $bindingKind = $this->bindingKind($package['records']);
                    $previousKind = $previousCheck['binding_kind'] ?? self::LEGACY_BINDING;
                    $this->parser->check($bindingKind === $previousKind, 'Wechsel der Abgabebindung benötigt einen tatsächlich neueren Prüfstand.');
                    $this->parser->check($previousCheck['work_id'] === (int) $work->id && $previousCheck['course_id'] === (int) $course->id
                        && ($previousCheck['roster'] === $this->rosterBinding($work)
                            || $previousCheck['roster'] === $this->rosterBinding($work, false)
                            || $this->matchesOriginalRosterOrder($work, $previousCheck, $identityBinding)), 'Geänderte Zielbasis benötigt einen neuen Prüfstand.');
                    $incoming = $receipts;
                    foreach ($package['records'] as $index => $record) {
                        $incoming = array_values(array_filter($incoming, fn (array $receipt): bool => $receipt['exercise_id'] !== $package['exercise_id'] || $receipt['participant_id'] !== $record['participant_id']));
                        $incoming[] = ['exercise_id' => $package['exercise_id'], 'participant_id' => $record['participant_id'], 'student_id' => $rows[$index]['student_id'], 'record' => $record];
                    }
                    $sameSubmissions = $previousCheck['bindings'] === $this->recordBindings($incoming, bindingKind: $bindingKind);
                    if ($bindingKind === self::LEGACY_BINDING && $previousDownload !== null && isset($package['final_download'])) {
                        $this->parser->finalDownload($previousDownload, $package['exercise_id'], array_column(array_values(array_filter($receipts,
                            fn (array $receipt): bool => $receipt['exercise_id'] === $package['exercise_id'])), 'record'));
                        $sameSubmissions = $this->recordBindings($receipts, $previousDownload) === $this->recordBindings($incoming, $package['final_download']);
                    }
                    $this->parser->check($previousCheck['bindings'] === $this->recordBindings($receipts, bindingKind: $previousKind) && $sameSubmissions, 'Geänderte Abgabefassung benötigt einen neuen Prüfstand.');
                }
            }
        }

        return ['target_work' => ['id' => $work->id, 'title' => $work->title, 'course_id' => $course->id, 'course_title' => $course->title],
            'deadline_context' => $deadlineContext,
            'identity_binding' => $identityBinding,
            'submission_check' => $submissionCheck,
            'teacher_absence_decision' => $package['teacher_absence_decision'] ?? null,
            'final_download' => $package['final_download'] ?? null,
            'exercise' => $package['exercise'], 'exercise_id' => $package['exercise_id'], 'maximum_minor' => $package['maximum_minor'],
            'package_checksum' => $package['package_checksum'], 'overview_pdf' => $package['overview_pdf'], 'rows' => $rows, 'can_import' => ! $blocked && ! $deadlineNeedsReview,
            'previous_overview_pdf' => collect($work->status['evaluation_pdfs'] ?? [])->firstWhere('student_id', null),
            'overview_will_replace' => ! $deadlineNeedsReview && $package['overview_pdf'] !== null && ! collect($work->status['assessment_json_packages'] ?? [])->contains('package_checksum', $package['package_checksum']),
            'hash' => hash('sha256', json_encode([$work->getAttributes(), $course->getAttributes(), $participants, $groups, $definition->toArray(), $bundle, $identityBinding], JSON_THROW_ON_ERROR))];
    }

    /** @param list<UploadedFile> $uploads
     * @param  list<string>  $createdPaths
     */
    public function apply(TeachingCourseWork $work, array $bundle, array $preview, array $uploads, array &$createdPaths): void
    {
        $package = $bundle['package'];
        $deadlineContext = $this->deadlineContext($work, $package);
        $this->parser->check(! ($deadlineContext['requires_review'] ?? false), $deadlineContext['message'] ?? 'Fristbezug benötigt erneute Prüfung.');
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
            $bindingKind = $this->bindingKind($package['records']);
            $bindings = $this->recordBindings($receipts, bindingKind: $bindingKind);
            $last = collect($checks)->last();
            if (! $last || $last['check']['check_checksum'] !== $package['submission_check']['check_checksum'] || $last['bindings'] !== $bindings
                || ($last['binding_kind'] ?? self::LEGACY_BINDING) !== $bindingKind
                || $last['roster'] !== $this->rosterBinding($work)) {
                $checks[] = ['check' => $package['submission_check'], 'work_id' => (int) $work->id, 'course_id' => (int) $work->teaching_course_id,
                    'roster' => $this->rosterBinding($work), 'bindings' => $bindings, 'binding_kind' => $bindingKind, 'imported_at' => now()->toISOString()];
            }
            $status['submission_checks'] = $checks;
        }
        $packages = $status['assessment_json_packages'] ?? [];
        if (! collect($packages)->contains('package_checksum', $package['package_checksum'])) {
            $packages[] = ['exercise_id' => $package['exercise_id'], 'package_checksum' => $package['package_checksum'], 'exercise' => $package['exercise'], 'imported_at' => now()->toISOString(),
                ...(isset($preview['identity_binding']) ? ['identity_binding' => $preview['identity_binding']] : []),
                ...(isset($package['teacher_absence_decision']) ? ['teacher_absence_decision' => $package['teacher_absence_decision']] : []),
                ...(isset($package['final_download']) ? ['final_download' => $package['final_download']] : [])];
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

    /** @return array<string, mixed> */
    public function identityBinding(TeachingCourseWork $work, User $actor, array $bundle): array
    {
        $source = $bundle['identity_export']['source'];
        $target = $this->identityExport->build($work, $actor)['context'];
        $message = 'Arbeitsdatenexport passt nicht vollständig und eindeutig zur Zielarbeit oder Kursliste.';
        foreach (['school', 'schoolyear', 'course', 'work'] as $section) {
            $id = $source[$section]['id'] ?? null;
            $this->parser->check((is_int($id) || is_string($id)) && preg_match('/\A[1-9][0-9]*\z/', (string) $id) === 1, $message);
        }
        $exerciseId = $bundle['package']['exercise_id'];
        $this->parser->check(! ctype_digit($exerciseId) || (string) $source['work']['id'] === $exerciseId, 'Ursprünglicher Arbeitsdatenexport und Bewertungspaket gehören zu verschiedenen Arbeiten.');
        foreach (['school' => ['name', 'short_name'], 'schoolyear' => ['name'], 'course' => ['title'], 'work' => ['title', 'type']] as $section => $fields) {
            foreach ($fields as $field) {
                $value = $source[$section][$field] ?? null;
                $this->parser->check(is_string($value) && SchooltoolAssessmentJson::normalize($value) === SchooltoolAssessmentJson::normalize((string) $target[$section][$field]), $message);
            }
        }
        $classes = function (mixed $values) use ($message): array {
            $this->parser->check(is_array($values) && array_is_list($values) && count(array_filter($values, 'is_string')) === count($values), $message);
            $values = array_map(SchooltoolAssessmentJson::normalize(...), $values);
            sort($values, SORT_STRING);

            return $values;
        };
        $this->parser->check($classes($source['course']['classes'] ?? null) === $classes($target['course']['classes'])
            && ($source['work']['is_group_work'] ?? null) === $target['work']['is_group_work']
            && array_key_exists('scheduled_date', $source['work']) && $source['work']['scheduled_date'] === $target['work']['scheduled_date']
            && isset($source['work']['maximum_points']) && is_numeric($source['work']['maximum_points'])
            && BigDecimal::of((string) $source['work']['maximum_points'])->isEqualTo((string) $target['work']['maximum_points']), $message);
        $roster = $source['course_roster'] ?? [];
        $this->parser->check(is_array($roster) && ($roster['complete'] ?? null) === true && ($roster['scope'] ?? null) === 'all_non_deleted_course_assignments'
            && is_array($roster['participants'] ?? null) && array_is_list($roster['participants']) && count($roster['participants']) === count($target['course_roster']['participants']), $message);
        $key = function (array $person, bool $includeEmail = true) use ($message): string {
            $parts = [];
            foreach ($includeEmail ? ['first_name', 'last_name', 'class_name', 'email'] : ['first_name', 'last_name', 'class_name'] as $field) {
                $value = $person[$field] ?? null;
                $this->parser->check(is_string($value) && trim($value) !== '' && ($field !== 'email' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false), $message);
                $parts[] = SchooltoolAssessmentJson::normalize($value);
            }
            $this->parser->check(is_bool($person['active'] ?? null), $message);

            return json_encode([$parts, $person['active']], JSON_THROW_ON_ERROR);
        };
        $targetPeople = [];
        foreach ($target['course_roster']['participants'] as $person) {
            $personKey = $key($person, false);
            $this->parser->check(! isset($targetPeople[$personKey]) && is_string($person['schooltool_person_id']) && preg_match('/\A[1-9][0-9]*\z/', $person['schooltool_person_id']) === 1, $message);
            $targetPeople[$personKey] = $person;
        }
        $map = [];
        $sourcePeople = [];
        $sourceKeys = [];
        foreach ($roster['participants'] as $person) {
            $this->parser->check(is_array($person), $message);
            $personKey = $key($person, false);
            $id = $person['schooltool_person_id'] ?? null;
            $this->parser->check(is_string($id) && preg_match('/\A[1-9][0-9]*\z/', $id) === 1 && ! isset($map[$id]) && ! isset($sourceKeys[$personKey]) && isset($targetPeople[$personKey]), $message);
            $this->parser->check(($person['participant_id'] ?? null) === 'user:'.$id, $message);
            $matched = $targetPeople[$personKey];
            if ($id !== $matched['schooltool_person_id']) {
                $this->parser->check($key($person) === $key($matched), $message);
            }
            $map[$id] = $targetPeople[$personKey]['schooltool_person_id'];
            $sourcePeople[$id] = $person;
            $sourceKeys[$personKey] = true;
        }
        foreach ($bundle['package']['records'] as $record) {
            $this->checkBindingIdentity($record['identity'], $sourcePeople, $message);
        }
        $groups = function (mixed $groups, ?array $mapping = null) use ($message): array {
            $this->parser->check(is_array($groups) && array_is_list($groups), $message);
            $result = [];
            foreach ($groups as $group) {
                $this->parser->check(is_array($group) && array_key_exists('name', $group) && ($group['name'] === null || is_string($group['name']))
                    && array_key_exists('date', $group) && ($group['date'] === null || is_string($group['date'])) && is_array($group['schooltool_student_ids'] ?? null), $message);
                $ids = [];
                foreach ($group['schooltool_student_ids'] as $id) {
                    $this->parser->check((is_int($id) || is_string($id)) && ($mapping === null || isset($mapping[(string) $id])), $message);
                    $ids[] = $mapping !== null ? $mapping[(string) $id] : (string) $id;
                }
                sort($ids, SORT_STRING);
                $this->parser->check(count(array_unique($ids)) === count($ids), $message);
                $result[] = json_encode([SchooltoolAssessmentJson::normalize((string) $group['name']), $group['date'], $ids], JSON_THROW_ON_ERROR);
            }
            sort($result, SORT_STRING);

            return $result;
        };
        $this->parser->check($groups($source['work']['groups'] ?? null, $map) === $groups($target['work']['groups']), $message);
        ksort($map, SORT_STRING);

        $people = $this->participants($work->teachingCourse);
        usort($people, fn (array $left, array $right): int => $left['student_id'] <=> $right['student_id']);
        $originalGroups = array_map(fn (array $group): array => [
            'name' => $group['name'] ?? '', 'student_ids' => $this->sortedIds(array_map(fn ($id): int => (int) $map[(string) $id], $group['schooltool_student_ids'])),
        ], $source['work']['groups']);

        return ['source_sha256' => $bundle['identity_export']['sha256'], 'student_ids' => $map,
            'source_people' => $sourcePeople,
            'source_roster' => SchooltoolAssessmentJson::digest([$people, (string) $work->teachingCourse->title, (bool) $work->is_group_work, $originalGroups]),
            'target_fingerprint' => SchooltoolAssessmentJson::digest([$target['school'], $target['schoolyear'], $target['course'], $target['work'], $target['course_roster']])];
    }

    /** @param array<string, mixed> $check
     * @param  array<string, mixed>|null  $binding
     */
    private function matchesOriginalRosterOrder(TeachingCourseWork $work, array $check, ?array $binding): bool
    {
        if ($work->is_group_work || $binding === null || $check['roster'] !== $binding['source_roster']) {
            return false;
        }
        $previous = collect($work->status['assessment_json_packages'] ?? [])->last(fn (array $package): bool => $package['exercise_id'] === $check['check']['exercise_id']);
        $savedBinding = $previous['identity_binding'] ?? null;

        return $savedBinding !== null && $savedBinding['source_sha256'] === $binding['source_sha256']
            && $savedBinding['student_ids'] === $binding['student_ids'];
    }

    public function checkBindingIdentity(array $identity, array $people, string $message = 'Person im Beleg widerspricht dem ursprünglichen Arbeitsdatenexport.'): void
    {
        $person = $people[$identity['schooltool_person_id'] ?? ''] ?? null;
        $this->parser->check(is_array($person), $message);
        foreach (['first_name', 'last_name', 'class_name'] as $field) {
            $this->parser->check(isset($identity[$field]) && SchooltoolAssessmentJson::normalize($identity[$field]) === SchooltoolAssessmentJson::normalize($person[$field]), $message);
        }
    }

    public function boundStudentId(array $identity, array $binding, ?string $email = null): string
    {
        $people = $binding['source_people'];
        if (($identity['schooltool_person_id'] ?? null) === null) {
            $matches = array_filter($people, function (array $person) use ($identity, $email, $binding): bool {
                foreach (['first_name', 'last_name', 'class_name'] as $field) {
                    if (SchooltoolAssessmentJson::normalize($person[$field]) !== SchooltoolAssessmentJson::normalize($identity[$field])) {
                        return false;
                    }
                }

                return $email !== null && ($binding['student_ids'][$person['schooltool_person_id']] === $person['schooltool_person_id']
                    || mb_strtolower(trim($email)) === mb_strtolower(trim($person['email'])));
            });
            $this->parser->check(count($matches) === 1, 'Belegperson ist im ursprünglichen Arbeitsdatenexport nicht eindeutig belegt.');
            $identity['schooltool_person_id'] = (string) array_key_first($matches);
        }
        $this->checkBindingIdentity($identity, $people);
        $person = $people[$identity['schooltool_person_id']];
        $sameId = $binding['student_ids'][$identity['schooltool_person_id']] === $identity['schooltool_person_id'];
        $this->parser->check($sameId || $email === null || mb_strtolower(trim($email)) === mb_strtolower(trim($person['email'])), 'Belegadresse widerspricht dem ursprünglichen Arbeitsdatenexport.');

        return $binding['student_ids'][$identity['schooltool_person_id']];
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

    private function rosterBinding(TeachingCourseWork $work, bool $sortGroups = true): string
    {
        $ids = $this->expectedStudentIds($work);
        $people = array_values(array_filter($this->participants($work->teachingCourse), fn (array $person): bool => in_array($person['student_id'], $ids, true)));
        usort($people, fn (array $left, array $right): int => $left['student_id'] <=> $right['student_id']);
        $groups = array_map(fn (array $group): array => ['name' => $group['name'] ?? '', 'student_ids' => $this->sortedIds($group['student_ids'] ?? [])], $this->sync->groupsForWork($work));
        if ($sortGroups) {
            usort($groups, fn (array $left, array $right): int => strcmp(SchooltoolAssessmentJson::canonical($left), SchooltoolAssessmentJson::canonical($right)));
        }

        return SchooltoolAssessmentJson::digest([$people, (string) $work->teachingCourse->title, (bool) $work->is_group_work, $groups]);
    }

    /** @param list<array<string, mixed>> $records */
    private function bindingKind(array $records): string
    {
        return array_key_exists('submission_sha256', $records[0]) ? self::SUBMISSION_BINDING : self::LEGACY_BINDING;
    }

    /** @return array<int, string> */
    private function recordBindings(array $receipts, ?array $download = null, string $bindingKind = self::LEGACY_BINDING): array
    {
        $this->parser->check(in_array($bindingKind, [self::LEGACY_BINDING, self::SUBMISSION_BINDING], true), 'Unbekannte Abgabebindung.');
        $bindings = [];
        foreach ($receipts as $receipt) {
            $record = $receipt['record'];
            $source = $record['source_fingerprint'] ?? null;
            if ($bindingKind === self::SUBMISSION_BINDING) {
                $this->parser->check(isset($record['submission_sha256']) && is_array($record['submission_sha256']), 'Gespeicherte persönliche Abgabeprüfsummen fehlen.');
                $hashes = $record['submission_sha256'];
                sort($hashes, SORT_STRING);
                $source = ['submission_sha256' => $hashes];
            }
            if ($download !== null && $download['exercise_id'] === $receipt['exercise_id']) {
                $person = collect($download['participants'])->firstWhere('participant_id', $receipt['participant_id']);
                $this->parser->check($person !== null, 'Abschlussbestand enthält nicht alle gebundenen Personen.');
                $hashes = $person['submission_sha256'];
                sort($hashes, SORT_STRING);
                $source = ['submission_sha256' => $hashes];
            }
            $bindings[(int) $receipt['student_id']] = SchooltoolAssessmentJson::digest([$receipt['exercise_id'], $receipt['participant_id'], $record['identity'] ?? [],
                $record['submission_state'] ?? null, $source, $record['email_collected_at'] ?? null]);
        }
        ksort($bindings, SORT_NUMERIC);

        return $bindings;
    }

    /** @param array<string, mixed> $package
     * @return array{state: string, current_at: ?string, current_date: ?string, current_time: ?string, source_at: string, requires_review: bool, message: string}|null
     */
    private function deadlineContext(TeachingCourseWork $work, array $package): ?array
    {
        $deadlines = [];
        foreach (['submission_check', 'final_download', 'teacher_absence_decision'] as $field) {
            if (isset($package[$field])) {
                $deadlines[] = $this->parser->instant($package[$field]['deadline_at']);
            }
        }
        if ($deadlines === []) {
            return null;
        }
        $date = $work->finish_until_date?->format('Y-m-d');
        $time = $work->finish_until_time === null || $work->finish_until_time === '' ? null : (string) $work->finish_until_time;
        $zone = new DateTimeZone('Europe/Vienna');
        $source = $deadlines[0];
        $current = null;
        $state = 'missing_target';
        $message = 'Aktuelle Lehrkraftfrist mit Datum und Uhrzeit in der Zielarbeit einstellen und speichern. Die Paketfrist wird nicht automatisch übernommen.';
        if ($date !== null && $time !== null) {
            $this->parser->check(preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $time) === 1, 'Ungültige Ziel-Fristuhrzeit. Zielarbeit prüfen und speichern.');
            $current = (new DateTimeImmutable($date.' '.$time.':00', $zone))->setTimezone(new DateTimeZone('UTC'));
            $this->checkDeadline($work, ['deadline_at' => $current->format('Y-m-d\TH:i:s\Z')]);
            $matches = collect($deadlines)->every(fn (DateTimeImmutable $deadline): bool => $deadline == $current);
            $state = $matches ? 'matched' : 'changed';
            $message = $matches ? 'Paketbelege entsprechen der aktuellen Lehrkraftfrist.'
                : 'Aktuelle Lehrkraftfrist: '.$current->setTimezone($zone)->format('d.m.Y H:i').' Uhr. Historische Paketfrist: '.$source->setTimezone($zone)->format('d.m.Y H:i').' Uhr (Europe/Vienna). Abgabeprüfnachweis und fristabhängige Bewertungen mit der aktuellen Frist erneut prüfen und aktualisiert exportieren. Die Lehrkraftfrist bleibt unverändert.';
        }

        return ['state' => $state, 'current_at' => $current?->format('Y-m-d\TH:i:s\Z'), 'current_date' => $date, 'current_time' => $time,
            'source_at' => $source->format('Y-m-d\TH:i:s\Z'), 'requires_review' => $state !== 'matched', 'message' => $message];
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
                && in_array($receipt['roster'], [$this->rosterBinding($work), $this->rosterBinding($work, false)], true)
                && $receipt['bindings'] === $this->recordBindings($imports, bindingKind: $receipt['binding_kind'] ?? self::LEGACY_BINDING), 'Abgabeprüfung: Zielbasis geändert.');
            $records = array_column(array_values(array_filter($imports, fn (array $item): bool => $item['exercise_id'] === $check['exercise_id'])), 'record');
            $package = collect($work->status['assessment_json_packages'] ?? [])->last(fn (array $item): bool => $item['exercise_id'] === $check['exercise_id']);
            $absenceIds = $package['teacher_absence_decision']['participant_ids'] ?? [];
            if (isset($package['final_download'])) {
                $download = $package['final_download'];
                $this->parser->finalDownload($download, $check['exercise_id'], $records);
                $this->checkDeadline($work, $download);
                $absenceIds = array_merge($absenceIds, array_column(array_filter($download['participants'], fn (array $person): bool => $person['submission_sha256'] === []), 'participant_id'));
            }
            $this->parser->submissionCheck($check, $check['exercise_id'], $records, $absenceIds);
            $this->checkDeadline($work, $check);

            return ['complete' => $check['state'] === 'complete', 'checked_at' => $check['checked_at'], 'student_ids' => $this->expectedStudentIds($work),
                'deadline_date' => $work->finish_until_date->format('Y-m-d'), 'deadline_time' => $work->finish_until_time];
        } catch (ValidationException) {
            return $unknown;
        }
    }
}
