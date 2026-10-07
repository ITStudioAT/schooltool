<?php

namespace Tests\Support;

use App\Support\SchooltoolAssessmentJson;
use Illuminate\Http\UploadedFile;

class TeachingWorkJsonFixture
{
    public static function package(): array
    {
        return json_decode(file_get_contents(__DIR__.'/schooltool-json-v1.beispiel.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function sign(array $package): array
    {
        foreach ($package['records'] as &$record) {
            unset($record['record_checksum']);
            $record['record_checksum'] = SchooltoolAssessmentJson::digest($record);
        }
        unset($record, $package['package_checksum']);
        $package['package_checksum'] = SchooltoolAssessmentJson::digest($package);

        return $package;
    }

    public static function upload(array $package): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Schooltool-Bewertungen.json', json_encode(self::sign($package), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function withSubmissionCheck(array $package, string $state = 'complete'): array
    {
        if (count($package['records']) === 1) {
            $open = $package['records'][0];
            $open['participant_id'] = 'FICTION-002';
            $open['identity']['first_name'] = 'Bea';
            $open['identity']['last_name'] = 'Beta';
            $open['evaluation_state'] = 'open';
            $open['total_minor'] = null;
            foreach ($open['criteria'] as &$criterion) {
                $criterion['earned_minor'] = null;
            }
            unset($criterion);
            $package['records'][] = $open;
        }
        foreach ($package['records'] as &$record) {
            $record['submission_state'] = 'received';
        }
        unset($record);
        $hash = str_repeat('a', 64);
        $ids = array_column($package['records'], 'participant_id');
        sort($ids, SORT_STRING);
        $check = ['schema_version' => 1, 'kind' => 'schooltool.submission-check', 'exercise_id' => $package['exercise_id'], 'timezone' => 'Europe/Vienna',
            'deadline_at' => '2026-10-06T10:00:00Z', 'checked_at' => '2026-10-06T10:01:00Z', 'completed_at' => $state === 'complete' ? '2026-10-06T10:01:00Z' : null,
            'roster_fingerprint' => SchooltoolAssessmentJson::digest($ids), 'roster_evidence_sha256' => [$hash], 'deadline_evidence_sha256' => [$hash],
            'participants' => array_map(fn (string $id): array => ['participant_id' => $id, 'dispatched_at' => '2026-10-04T08:00:00Z', 'email_result' => 'received',
                'other_result' => 'not_received', 'evidence_sha256' => [$hash], 'gaps' => []], $ids),
            'coverage' => array_map(fn (string $mailbox): array => ['mailbox' => $mailbox, 'scope' => $state === 'complete' ? 'server_all_folders' : 'local_cache',
                'verified' => true, 'start_at' => '2026-10-04T08:00:00Z', 'end_at' => '2026-10-06T10:01:00Z', 'evidence_sha256' => [$hash], 'gaps' => []], ['guenther.kron@cdgym.at', 'guenther.kron@bildung.gv.at']),
            'unresolved_candidates' => [], 'gaps' => $state === 'complete' ? [] : ['mailbox_incomplete'], 'state' => $state];
        $check['check_checksum'] = SchooltoolAssessmentJson::digest($check);
        $package['submission_check'] = $check;

        return $package;
    }
}
