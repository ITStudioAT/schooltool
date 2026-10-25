<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\MultipleChoice\McTestService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TeachingWorkMultipleChoice
{
    public function __construct(private McTestService $tests) {}

    /** @return array<string, mixed> */
    public function options(TeachingCourseWork $work, User $actor, ?string $date, array $workContext = []): array
    {
        if (! Schema::hasTable('mc_tests') || ! Schema::hasTable('mc_catalogs') || ! Schema::hasTable('mc_attempts')) {
            return ['available' => false, 'tests' => [], 'catalogs' => [], 'linked_test_id' => null];
        }
        $tests = McTest::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
            ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->orderBy('title')
            ->get(['id', 'catalog_id', 'title', 'status', 'question_count', 'total_points', 'duration_seconds']);
        $catalogs = McCatalog::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
            ->orderBy('title')->get(['id', 'title', 'questions'])->map(function (McCatalog $catalog): array {
                $count = count(array_filter($catalog->questions ?? [], fn (mixed $question): bool => is_array($question)
                    && ($question['active'] ?? false) === true && ($question['reviewed'] ?? false) === true));

                return ['id' => $catalog->id, 'title' => $catalog->title, 'reviewed_question_count' => $count];
            });
        $binding = $work->status['multiple_choice_tests'][$this->slot($work, $date)] ?? null;

        return ['available' => true, 'configuration' => $this->configuration($work, $actor, $date, $workContext),
            'tests' => $tests->map(fn (McTest $test): array => [...$test->toArray(),
                'catalog_title' => $catalogs->firstWhere('id', $test->catalog_id)['title'] ?? null]),
            'catalogs' => $catalogs->filter(fn (array $catalog): bool => $catalog['reviewed_question_count'] >= 10)->values(),
            'linked_test_id' => ($binding['work_id'] ?? null) === $work->id && $tests->contains('id', $binding['test_id'] ?? null)
                ? $binding['test_id'] : null,
            'expected_test_id' => isset($binding['test_id']) ? (int) $binding['test_id'] : null,
            'linked_mc_points' => $binding['points_plan']['mc_points'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $participants
     * @return array<string, mixed>
     */
    public function prepare(TeachingCourseWork $work, User $actor, array $data, array $participants, array $workContext = []): array
    {
        $configuration = $this->configuration($work, $actor, $data['date'] ?? null, $workContext, true);
        if ($configuration['locked']) {
            throw ValidationException::withMessages(['mc_mode' => $configuration['message']]);
        }
        $maximum = $configuration['maximum_points'];
        abort_unless($maximum !== null && round((float) ($data['expected_maximum_points'] ?? 0), 2) === $maximum,
            409, 'Die LF-Gesamtpunktzahl ist nicht bestätigt oder wurde inzwischen geändert. Bitte die MC-Auswahl erneut öffnen.');
        $mcPoints = $data['mc_points'] ?? null;
        if ($maximum === null || ! is_numeric($mcPoints) || (float) $mcPoints <= 0 || (float) $mcPoints > $maximum) {
            throw ValidationException::withMessages(['mc_points' => 'Bitte den MC-Anteil innerhalb der gespeicherten LF-Gesamtpunkte festlegen. Die Gesamtpunktzahl muss positiv und bekannt sein.']);
        }
        $pointsPlan = ['maximum_points' => $maximum, 'mc_points' => round((float) $mcPoints, 2),
            'other_points' => round($maximum - (float) $mcPoints, 2), 'requires_criteria_reconciliation' => true];
        $slot = $this->slot($work, $data['date'] ?? null);
        $binding = $work->status['multiple_choice_tests'][$slot] ?? null;
        $requestHash = hash('sha256', json_encode([$data, array_column($participants, 'schooltool_person_id')], JSON_THROW_ON_ERROR));
        $repeated = ($binding['request_id'] ?? null) === $data['request_id'] && ($binding['request_hash'] ?? null) === $requestHash;
        abort_unless($repeated || (isset($binding['test_id']) ? (int) $binding['test_id'] : null) === (isset($data['expected_test_id']) ? (int) $data['expected_test_id'] : null), 409,
            'Die MC-Verknüpfung wurde inzwischen geändert. Bitte den Dialog erneut öffnen.');
        $studentIds = array_column($participants, 'schooltool_person_id');
        abort_unless(count($studentIds) === count($participants) && ! in_array(null, $studentIds, true), 422,
            'Für den MC-Test benötigen alle Prüfungskandidaten ein zugeordnetes Schülerkonto.');
        $studentIds = array_map('intval', $studentIds);
        if ($data['mc_mode'] === 'existing' || $repeated) {
            $test = McTest::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)
                ->lockForUpdate()->findOrFail($repeated ? $binding['test_id'] : $data['test_id']);
            if ($test->snapshot === null && ! $test->attempts()->exists() && $test->status === 'draft') {
                $test = $this->tests->prepare($test, $studentIds);
            }
            $assigned = $test->attempts()->pluck('student_id')->map(fn (mixed $id): int => (int) $id)->all();
            abort_unless(array_diff($studentIds, $assigned) === [], 422,
                'Der ausgewählte MC-Test enthält nicht alle Prüfungskandidaten dieser Leistungsfeststellung.');
        } else {
            $catalog = McCatalog::query()->where('school_id', $work->teachingCourse->school_id)->where('owner_id', $actor->id)
                ->lockForUpdate()->findOrFail($data['catalog_id']);
            $test = McTest::query()->create([
                'school_id' => $work->teachingCourse->school_id, 'schoolyear_id' => $work->teachingCourse->schoolyear_id,
                'owner_id' => $actor->id, 'catalog_id' => $catalog->id, 'title' => $this->newTestTitle($work, $data['date'] ?? $workContext['scheduled_date'] ?? null),
                'status' => 'draft', 'question_count' => 10, 'total_points' => $data['total_points'],
                'duration_seconds' => $data['duration_minutes'] * 60, 'lead_seconds' => 60,
                'start_mode' => 'common', 'exam_mode' => false, 'balance_types' => true,
                'shuffle_questions' => true, 'shuffle_options' => true,
            ]);
            $test = $this->tests->prepare($test, $studentIds);
        }
        abort_unless(is_numeric($test->total_points) && (float) $test->total_points > 0, 422,
            'Der MC-Test benötigt eine positive Gesamtpunktzahl. Bitte den Test zuerst prüfen.');
        $pointsPlan['test_maximum_points'] = round((float) $test->total_points, 2);
        $status = $work->status ?? [];
        $status['multiple_choice_tests'][$slot] = ['work_id' => $work->id, 'test_id' => $test->id,
            'request_id' => $data['request_id'], 'request_hash' => $requestHash, 'points_plan' => $pointsPlan];
        $work->status = $status;
        $work->save();

        return ['test_id' => $test->id, 'catalog_id' => $test->catalog_id, 'title' => $test->title, 'points_plan' => $pointsPlan,
            'status' => $test->status, 'question_count' => $test->question_count,
            'total_points' => $test->total_points, 'duration_seconds' => $test->duration_seconds,
            'question_pool' => $test->snapshot['questions'] ?? [],
            'access_links_ready' => $test->status === 'published' && $test->access_links_disabled_at === null,
            'exercise_export_url' => $test->status === 'published' && $test->access_links_disabled_at === null
                ? route('admin.multiple-choice.tests.exercise-export', ['test' => $test->id]) : null];
    }

    /** @return array{locked: bool, message: ?string, maximum_points: ?float} */
    public function configuration(TeachingCourseWork $work, User $actor, ?string $date, array $workContext, bool $refresh = false): array
    {
        $locked = app(TeachingWorkFolderImport::class)->availabilitySources($work, [], []) !== [];
        $message = $locked ? 'Die Leistungsfeststellung wurde bereits versendet oder bereitgestellt. MC-Fragen können nicht mehr ergänzt oder geändert werden.' : null;
        $modules = app(DropboxModuleLinks::class);
        $link = $modules->current($work);
        if (! $locked && $link) {
            $connection = DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')->find($link->dropbox_connection_id);
            if (! $connection) {
                $locked = true;
                $message = 'Der Aufgabenversand kann nicht geprüft werden. Bitte die Ordnerverbindung und den LF-Status erneut prüfen.';
            } else {
                $exercise = $modules->exercise($connection, $work, $link, $date, refresh: $refresh, initial: false);
                $dispatch = $exercise['email_dispatch'] ?? [];
                if (in_array($dispatch['status'] ?? null, ['sent', 'partial'], true) || ($dispatch['confirmed_count'] ?? 0) > 0) {
                    $locked = true;
                    $message = 'Der Aufgabenversand hat bereits begonnen. MC-Fragen können nicht mehr ergänzt oder geändert werden.';
                } elseif (($exercise['status'] ?? 'unknown') === 'unknown'
                    || (($exercise['status'] ?? null) === 'found' && ($dispatch['status'] ?? 'unknown') === 'unknown')) {
                    $locked = true;
                    $message = 'Der Versandstand ist unklar. Bitte zuerst „Erneut prüfen“ ausführen; MC-Fragen werden bis dahin nicht geändert.';
                }
            }
        }
        $maximum = $workContext['maximum_points'] ?? null;

        return ['locked' => $locked, 'message' => $message,
            'maximum_points' => is_numeric($maximum) && (float) $maximum > 0 ? round((float) $maximum, 2) : null];
    }

    private function newTestTitle(TeachingCourseWork $work, ?string $date): string
    {
        $course = $work->teachingCourse;
        $courseTitle = trim($course->title);
        $classes = array_values(array_unique($course->classes ?? []));
        $cohort = implode('-', $classes);
        if (count($classes) === 1 && preg_match('/\A(.+?)\s+-\s+(.+?)\s+-\s+([1-9][0-9]*)\z/u', $courseTitle, $parts) === 1
            && trim($parts[2]) === $classes[0]) {
            $cohort = $classes[0].'_Gruppe-'.(int) $parts[3];
        }
        $segments = array_filter([$date ?? $work->date_for_all_groups?->format('Y-m-d'), trim($work->title) ?: $courseTitle, $cohort]);
        $segments = array_map(fn (string $segment): string => trim(preg_replace('/[^\p{L}\p{N}._-]+/u', '-', $segment), '-'), $segments);

        return mb_substr(implode('_', $segments), 0, 255);
    }

    private function slot(TeachingCourseWork $work, ?string $date): string
    {
        return $date ?? $work->date_for_all_groups?->format('Y-m-d') ?? 'all';
    }
}
