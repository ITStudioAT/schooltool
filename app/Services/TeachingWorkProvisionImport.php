<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Support\SchooltoolAssessmentJson;
use Illuminate\Support\Facades\Storage;

class TeachingWorkProvisionImport
{
    public function __construct(private SchooltoolAssessmentJson $parser, private TeachingWorkJsonImport $assessments, private TeachingCourseWorkEntrySyncService $entries) {}

    /** @return array<string, mixed> */
    public function parse(string $text): array
    {
        return $this->parser->provision($text);
    }

    /** @param array<string, mixed> $provision
     * @return array<string, mixed>
     */
    public function preview(TeachingCourseWork $work, array $provision, string $sha256): array
    {
        $participants = $this->assessments->participants($work->teachingCourse);
        $groups = $this->entries->groupsForWork($work);
        $previous = $work->status['provision_imports'] ?? [];
        $rows = [];
        $targets = [];
        foreach ($provision['participants'] as $person) {
            $identity = $person['identity'];
            $matches = array_values(array_filter($participants, function (array $participant) use ($identity): bool {
                foreach (['first_name', 'last_name', 'class_name'] as $field) {
                    if (SchooltoolAssessmentJson::normalize($participant[$field]) !== SchooltoolAssessmentJson::normalize($identity[$field])) {
                        return false;
                    }
                }

                return true;
            }));
            $ids = array_values(array_unique(array_column($matches, 'student_id')));
            $studentId = count($ids) === 1 ? $ids[0] : null;
            $memberGroups = array_values(array_filter($groups, fn (array $group): bool => $studentId !== null && in_array($studentId, $group['student_ids'] ?? [], true)));
            $groupName = $work->is_group_work ? ($memberGroups[0]['name'] ?? '') : $work->teachingCourse->title;
            $events = array_values(array_filter($provision['events'], fn (array $event): bool => in_array($person['participant_id'], $event['participant_ids'], true)));
            $old = collect($previous)->first(fn (array $record): bool => $record['exercise_id'] === $provision['exercise_id'] && $record['participant_id'] === $person['participant_id']);
            $valid = $studentId !== null && count($memberGroups) === 1 && ! in_array($studentId, $targets, true)
                && SchooltoolAssessmentJson::normalize((string) $groupName) === SchooltoolAssessmentJson::normalize($identity['group_name'])
                && ($identity['schooltool_person_id'] === null || $identity['schooltool_person_id'] === (string) $studentId)
                && ($old === null || (int) $old['student_id'] === $studentId);
            $targets[] = $studentId;
            $rows[] = ['participant_id' => $person['participant_id'], 'identity' => $identity, 'student_id' => $studentId,
                'events' => $events, 'valid' => $valid, 'status' => ! $valid ? 'Person, Klasse, Gruppe oder Arbeitszuordnung nicht eindeutig – Import gesperrt'
                    : ($events === [] ? 'Keine direkte Bereitstellung für diese Person belegt' : 'Belegte Bereitstellung übernehmen; individueller Download nicht bestätigt')];
        }
        $records = [];
        foreach ($rows as $row) {
            foreach ($row['events'] as $event) {
                $records[] = ['student_id' => $row['student_id'], 'participant_id' => $row['participant_id'], 'exercise_id' => $provision['exercise_id'],
                    'event_id' => $event['event_id'], 'method' => $event['method'], 'available_at' => $event['available_at'],
                    'source_kind' => $event['source_kind'], 'evidence_sha256' => $event['evidence_sha256'], 'log_sha256' => $sha256, 'origin' => 'provision_import'];
            }
        }
        $expected = array_values(array_unique(array_intersect(array_column($participants, 'student_id'), collect($groups)->flatMap(fn (array $group): array => $group['student_ids'] ?? [])->all())));
        $bound = array_values(array_unique(array_filter($targets)));
        sort($expected, SORT_NUMERIC);
        sort($bound, SORT_NUMERIC);

        return ['exercise_id' => $provision['exercise_id'], 'exercise' => $provision['exercise'], 'rows' => $rows, 'records' => $records,
            'sha256' => $sha256, 'already_imported' => collect($work->status['dispatch_logs'] ?? [])->contains('sha256', $sha256),
            'roster_matches' => $expected === $bound, 'can_import' => collect($rows)->every('valid') && $expected === $bound,
            'hash' => hash('sha256', json_encode([$work->getAttributes(), $work->teachingCourse->getAttributes(), $participants, $groups, $provision, $sha256], JSON_THROW_ON_ERROR))];
    }

    /** @param array<string, mixed> $preview
     * @param  list<string>  $createdPaths
     */
    public function apply(TeachingCourseWork $work, array $preview, string $text, array &$createdPaths): void
    {
        $this->parser->check($preview['can_import'], 'Bereitstellungsbeleg enthält ungeklärte Zuordnungen.');
        $status = $work->status ?? [];
        if (collect($status['dispatch_logs'] ?? [])->contains('sha256', $preview['sha256'])) {
            return;
        }
        $path = "teaching/work_dispatches/{$work->teachingCourse->school_id}/{$work->id}/{$preview['sha256']}.json";
        if (! Storage::disk('local')->exists($path)) {
            $createdPaths[] = $path;
            $this->parser->check(Storage::disk('local')->put($path, $text), 'Bereitstellungsbeleg konnte nicht privat gespeichert werden.');
        }
        $status['dispatch_logs'][] = ['name' => 'Schooltool-Bereitstellung.json', 'sha256' => $preview['sha256'], 'file_path' => $path,
            'storage_disk' => 'local', 'origin' => 'provision_import', 'purpose' => 'provision',
            'available_at' => collect($preview['records'])->pluck('available_at')->sort()->last()];
        $status['provision_events'] = array_merge($status['provision_events'] ?? [], $preview['records']);
        foreach ($preview['rows'] as $row) {
            $status['provision_imports'][] = ['exercise_id' => $preview['exercise_id'], 'participant_id' => $row['participant_id'],
                'student_id' => $row['student_id'], 'log_sha256' => $preview['sha256'], 'origin' => 'provision_import'];
        }
        $work->status = $status;
        $work->save();
    }
}
