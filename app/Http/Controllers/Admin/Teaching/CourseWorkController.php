<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Exceptions\TeachingWorkDeletionConflict;
use App\Http\Controllers\Controller;
use App\Models\DropboxConnection;
use App\Models\Import116;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\MultipleChoice\McModuleTemplateSource;
use App\Services\MultipleChoice\McTestExchange;
use App\Services\MultipleChoice\McTestFolder;
use App\Services\MultipleChoice\McTestService;
use App\Services\TeachingAssessmentRuns;
use App\Services\TeachingCourseStudentActivity;
use App\Services\TeachingCourseStudentEntryService;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingCourseWorkService;
use App\Services\TeachingDirectAssessment;
use App\Services\TeachingWorkAiExport;
use App\Services\TeachingWorkCollectionStatus;
use App\Services\TeachingWorkDeadlineSync;
use App\Services\TeachingWorkDeletion;
use App\Services\TeachingWorkDispatchImport;
use App\Services\TeachingWorkExerciseGeneration;
use App\Services\TeachingWorkFolderImport;
use App\Services\TeachingWorkJsonImport;
use App\Services\TeachingWorkMarkdownImport;
use App\Services\TeachingWorkMultipleChoice;
use App\Services\TeachingWorkRefreshProgress;
use App\Services\TeachingWorkStudentDistribution;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CourseWorkController extends Controller
{
    public function studentDistributionPreview(TeachingCourseWork $course_work, TeachingWorkStudentDistribution $distribution): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);

        return response()->json(['data' => ['tests' => $distribution->mcReleasePreview($course_work, (int) $actor->id),
            'server_now' => now()->toIso8601String()]])->header('Cache-Control', 'private, no-store');
    }

    public function studentDistributionTestAction(Request $request, TeachingCourseWork $course_work, TeachingWorkStudentDistribution $distribution, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate(['action' => ['required', 'in:countdown,pause,resume,reset,stop,delete'], 'test_id' => ['required', 'integer', 'min:1'],
            'expected_version' => ['required', 'string', 'size:64'], 'expected_test_version' => ['required', 'string', 'size:64'],
            'confirmed' => ['sometimes', 'boolean']]);

        $result = DB::transaction(function () use ($course_work, $actor, $data, $distribution, $entries): array {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            abort_unless(hash_equals($distribution->version($work), $data['expected_version']), 409, 'Die Arbeit wurde inzwischen geändert. Bitte den Stand neu laden.');

            $tests = $distribution->runMcManagementAction($work, (int) $actor->id, (int) $data['test_id'], $data['action'],
                $data['expected_test_version'], (bool) ($data['confirmed'] ?? false));

            return ['tests' => $tests] + ($data['action'] === 'delete' ? ['work' => $entries->serializeWork($work->load('teachingCourseWorkGroupStudents'))] : []);
        }, 3);

        return response()->json(['data' => $result + ['server_now' => now()->toIso8601String()]])->header('Cache-Control', 'private, no-store');
    }

    public function studentDistribution(Request $request, TeachingCourseWork $course_work, TeachingWorkStudentDistribution $distribution, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate(['publish' => ['required', 'boolean'], 'release_now' => ['sometimes', 'boolean'], 'release_saved' => ['sometimes', 'boolean'],
            'retain_paused_release' => ['sometimes', 'boolean'], 'instructions' => ['nullable', 'string', 'max:20000'],
            'files' => ['sometimes', 'array', 'max:5'], 'files.*' => ['file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:8192'],
            'retained_file_ids' => ['sometimes', 'array', 'max:5'], 'retained_file_ids.*' => ['required', 'uuid', 'distinct'],
            'expected_version' => ['required', 'string', 'size:64'], 'mc_tests' => ['nullable', 'string', 'max:65536'],
            'expected_test_versions' => ['sometimes', 'array', 'min:1', 'max:2000'], 'expected_test_versions.*' => ['required', 'string', 'size:64']]);
        $createdPaths = [];
        $committed = false;
        try {
            $result = DB::transaction(function () use ($course_work, $actor, $data, $request, $distribution, $entries, &$createdPaths, &$committed): array {
                DB::afterCommit(function () use (&$committed): void {
                    $committed = true;
                });
                $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
                $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
                abort_unless(hash_equals($distribution->version($work), $data['expected_version']), 409, 'Die Arbeit wurde inzwischen geändert. Bitte neu öffnen.');
                if ($data['publish']) {
                    $releaseNow = (bool) ($data['release_now'] ?? false);
                    if (! $releaseNow && ! empty($work->status['student_distribution']['published_at']) && empty($work->status['student_distribution']['withdrawn_at'])) {
                        throw ValidationException::withMessages(['release_now' => 'Diese Aufgabe wurde bereits freigegeben. Bitte die bestehende Freigabe bewusst aktualisieren oder zurücknehmen.']);
                    }
                    if (! empty($data['release_saved'])) {
                        $distribution->releaseSavedWithScheduledTest($work, $releaseNow, $createdPaths);
                    } else {
                        $distribution->publishMcTests($work, $data['mc_tests'] ?? null, $releaseNow);
                        $distribution->publish($work, $data['instructions'] ?? '', $request->file('files', []), $createdPaths,
                            $data['retained_file_ids'] ?? [], $releaseNow, (bool) ($data['retain_paused_release'] ?? false));
                    }
                } else {
                    $distribution->pauseStudentRelease($work, (int) $actor->id, $data['expected_test_versions'] ?? null);
                }

                return $entries->serializeWork($work->load('teachingCourseWorkGroupStudents'));
            });
        } catch (Throwable $exception) {
            if ($committed) {
                report($exception);

                return response()->json(['message' => 'Die Freigabe wurde gespeichert, aber die automatische Abschlussplanung konnte nicht bestätigt werden. Bitte den aktuellen Stand erneut öffnen und prüfen.'], 500);
            }
            Storage::disk('local')->delete($createdPaths);
            throw $exception;
        }

        return response()->json(['data' => $result]);
    }

    public function deleteStudentDistributionPreparation(Request $request, TeachingCourseWork $course_work, TeachingWorkStudentDistribution $distribution, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate(['expected_version' => ['required', 'string', 'size:64']]);

        $result = DB::transaction(function () use ($course_work, $actor, $data, $distribution, $entries): array {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            abort_unless(hash_equals($distribution->version($work), $data['expected_version']), 409, 'Die Arbeit wurde inzwischen geändert. Bitte neu öffnen.');
            $distribution->deletePreparation($work);

            return $entries->serializeWork($work->load('teachingCourseWorkGroupStudents'));
        });

        return response()->json(['data' => $result]);
    }

    private const ManualCompletionFields = [
        'email_preparation_completed', 'task_dispatch_completed', 'submission_collection_completed',
        'evaluation_completed', 'publication_completed', 'notification_completed',
    ];

    public function emailPreparationPreview(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox, DropboxModuleLinks $modules): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $modules->preparedEmailPreview($dropbox->connection($actor), $course_work, $data['date'] ?? null)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function exerciseOptions(Request $request, TeachingCourseWork $course_work, TeachingWorkAiExport $export, TeachingWorkMultipleChoice $multipleChoice): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'check_module_questions' => ['sometimes', 'boolean'],
            'mc_selection' => ['nullable', Rule::in(['current', 'tests', 'template_points', 'catalogs', 'candidates'])],
            'catalog_id' => ['required_if:mc_selection,candidates', 'nullable', 'integer', 'min:1']]);
        if (isset($data['mc_selection'])) {
            if ($data['mc_selection'] === 'template_points') {
                return response()->json(['assessment_points' => $this->assessmentSource($course_work, $actor)['points']])
                    ->header('Cache-Control', 'private, no-store');
            }

            if ($data['mc_selection'] === 'catalogs') {
                $catalogs = McCatalog::query()->where('school_id', $course_work->teachingCourse->school_id)
                    ->where('owner_id', $course_work->teachingCourse->user_id)->orderBy('title')->orderBy('id')->get();

                return response()->json(['catalogs' => $catalogs->map(fn (McCatalog $catalog): array => [
                    'id' => $catalog->id, 'title' => $catalog->title,
                    'reviewed_question_count' => count($catalog->questionsForTesting()),
                ]), 'defaults' => $this->mcSelectionDefaults()])->header('Cache-Control', 'private, no-store');
            }

            if ($data['mc_selection'] === 'candidates') {
                return response()->json($this->mcCatalogCandidates($course_work, $data['date'] ?? null, (int) $data['catalog_id']))
                    ->header('Cache-Control', 'private, no-store');
            }

            return response()->json($this->mcSelectionOptions($course_work, $data['date'] ?? null, $data['mc_selection'] === 'tests'))
                ->header('Cache-Control', 'private, no-store');
        }
        if ($data['check_module_questions'] ?? false) {
            $link = app(DropboxModuleLinks::class)->current($course_work);
            abort_unless($link, 409, 'Für diese Arbeit ist kein Lernmodul bestätigt.');

            $source = app(McModuleTemplateSource::class);
            $catalog = $source->workCatalog($actor, $link);
            try {
                $assessmentPoints = $source->assessmentPoints($actor, $link);
            } catch (Throwable) {
                $assessmentPoints = null;
            }

            return response()->json(['module_questions' => $catalog !== null, 'assessment_points' => $assessmentPoints,
                'module_catalog' => $catalog ? ['count' => count($catalog['questions']), 'fingerprint' => $catalog['fingerprint']] : null])
                ->header('Cache-Control', 'private, no-store');
        }
        $preview = $export->buildExercise($course_work, $actor, false, $data['date'] ?? null);

        $options = $multipleChoice->options($course_work, $actor, $data['date'] ?? null, $preview['context']['work']);
        $test = $multipleChoice->linkedModuleTest($course_work, $actor, $data['date'] ?? null);
        $options['eligible_test'] = $test ? ['id' => $test->id, 'title' => $test->title, 'total_points' => $test->total_points] : null;

        return response()->json($options)->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function mcSelectionOptions(TeachingCourseWork $work, ?string $date, bool $list): array
    {
        $slot = $this->mcSelectionSlot($work, $date);
        $binding = $work->status['multiple_choice_tests'][$slot] ?? null;
        abort_if($binding !== null && (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $work->id
            || ! is_numeric($binding['test_id'] ?? null)), 409, 'Die vorhandene MC-Zuordnung ist nicht eindeutig.');
        $id = $binding === null ? null : (int) $binding['test_id'];
        $query = McTest::query()->where('school_id', $work->teachingCourse->school_id)
            ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id);
        $current = $id ? (clone $query)->find($id) : null;
        abort_if($id && ! $current, 409, 'Der zugeordnete MC-Test ist nicht eindeutig zugänglich.');
        $result = ['linked_test_id' => $id, 'linked_test_title' => $current?->title,
            'linked_catalog_id' => $current?->catalog_id, 'expected_test_id' => $id];
        if ($list) {
            try {
                $required = $this->mcSelectionStudents($work, $slot);
            } catch (ValidationException $exception) {
                if (! $current) {
                    throw $exception;
                }

                return $result + ['tests' => [['id' => $current->id, 'title' => $current->title, 'status' => $current->status]],
                    'message' => collect($exception->errors())->flatten()->first()];
            }
            $inactive = app(TeachingWorkStudentDistribution::class)->verifiedInactiveMcGroupAccounts($work, $slot);
            $tests = (clone $query)->orderBy('title')->orderBy('id')->get();
            $result['tests'] = $tests->filter(fn (McTest $test): bool => (int) $test->id === $id || $this->mcSelectionEligible($work, $test, $required, $inactive))
                ->map(fn (McTest $test): array => ['id' => $test->id, 'title' => $test->title, 'status' => $test->status])->values();
        }

        return $result;
    }

    /** @return array{question_count: int, total_points: int, duration_minutes: int} */
    private function mcSelectionDefaults(): array
    {
        return ['question_count' => 10, 'total_points' => 10, 'duration_minutes' => 20];
    }

    /** @return array<string, mixed> */
    private function mcCatalogCandidates(TeachingCourseWork $work, ?string $date, int $catalogId): array
    {
        $catalog = McCatalog::query()->where('school_id', $work->teachingCourse->school_id)
            ->where('owner_id', $work->teachingCourse->user_id)->find($catalogId);
        abort_unless($catalog, 404, 'Der gewählte MC-Katalog ist für diesen Kurs nicht verfügbar.');
        $options = ['defaults' => $this->mcSelectionDefaults(),
            'available_question_count' => min(2000, count($catalog->questionsForTesting()))];
        $slot = $this->mcSelectionSlot($work, $date);
        $binding = $work->status['multiple_choice_tests'][$slot] ?? null;
        abort_if($binding !== null && (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $work->id
            || ! is_numeric($binding['test_id'] ?? null)), 409, 'Die vorhandene MC-Zuordnung ist nicht eindeutig.');
        $currentId = $binding ? (int) $binding['test_id'] : null;
        $tests = McTest::query()->where('school_id', $work->teachingCourse->school_id)
            ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id)
            ->where('catalog_id', $catalogId)->orderBy('id')->get();
        $current = $currentId ? $tests->firstWhere('id', $currentId) : null;
        if ($current) {
            return ['candidates' => [$this->mcSelectionTestDetails($current)]] + $options;
        }
        $required = $this->mcSelectionStudents($work, $slot);
        $inactive = app(TeachingWorkStudentDistribution::class)->verifiedInactiveMcGroupAccounts($work, $slot);
        $candidates = [];
        $unconfirmed = false;
        foreach ($tests as $test) {
            $proof = $test->snapshot['schooltool_work'] ?? null;
            $completeProof = is_array($proof) && is_int($proof['work_id'] ?? null)
                && is_int($proof['course_id'] ?? null) && is_string($proof['slot'] ?? null);
            if (is_array($proof) && ($proof['work_id'] ?? null) === (int) $work->id
                && ($proof['course_id'] ?? null) === (int) $work->teaching_course_id
                && ($proof['slot'] ?? null) === $slot && $this->mcSelectionEligible($work, $test, $required, $inactive, allowStarted: true)) {
                $candidates[] = $this->mcSelectionTestDetails($test);
            } elseif (! $completeProof && ($deadline = $work->finalDeadlineInstant()) !== null && $test->start_mode === 'individual'
                && $test->closes_at?->equalTo($deadline)
                && $test->snapshot !== null && ! $this->mcSelectionReferencedElsewhere($work, $test, $slot)
                && $this->mcSelectionMatchesGroup($test->attempts()->get(['id', 'student_id']), $required, $inactive)) {
                $unconfirmed = true;
            }
        }
        abort_if($unconfirmed, 409, 'Ein vorhandener Test dieses Katalogs könnte zur Gruppe und Frist gehören. Seine Zuordnung zur Arbeit ist nicht belegt; bitte zuerst den Test und die Durchführung klären.');

        return ['candidates' => $candidates] + $options;
    }

    /** @return array<string, mixed> */
    private function mcSelectionTestDetails(McTest $test): array
    {
        return ['id' => $test->id, 'title' => $test->title, 'status' => $test->status,
            'question_count' => $test->question_count, 'total_points' => (float) $test->total_points,
            'duration_minutes' => $test->duration_seconds / 60];
    }

    private function mcSelectionSlot(TeachingCourseWork $work, ?string $date): string
    {
        $dates = array_values(array_unique(array_filter([$work->date_for_all_groups?->format('Y-m-d'), ...array_column($work->groups ?? [], 'date')])));
        if ($date !== null && ! in_array($date, $dates, true)) {
            throw ValidationException::withMessages(['date' => 'Der ausgewählte Termin ist nicht als Durchführungstermin dieser Arbeit gespeichert.']);
        }

        return $date ?? $work->date_for_all_groups?->format('Y-m-d') ?? 'all';
    }

    /** @return list<int> */
    private function mcSelectionStudents(TeachingCourseWork $work, string $slot, bool $lock = false): array
    {
        return app(TeachingWorkStudentDistribution::class)->verifiedMcGroupAccounts($work, $slot, $lock);
    }

    /** @param list<int> $required
     * @param  list<int>  $inactive
     */
    private function mcSelectionEligible(TeachingCourseWork $work, McTest $test, array $required, array $inactive, bool $lock = false, bool $allowStarted = false): bool
    {
        if (! in_array($test->status, ['draft', 'published', 'withdrawn'], true) || (! $allowStarted && $test->starts_at !== null)
            || ! in_array($test->start_mode, ['individual', 'common'], true) || $test->snapshot === null
            || $test->access_links_disabled_at !== null) {
            return false;
        }
        $query = $test->attempts()->orderBy('id');
        $attempts = ($lock ? $query->lockForUpdate() : $query)->get();
        if (! $this->mcSelectionMatchesGroup($attempts, $required, $inactive) || $attempts->contains(fn ($attempt): bool => (in_array((int) $attempt->student_id, $required, true) && ! filled($attempt->access_token))
            || (! $allowStarted && ($attempt->started_at !== null || $attempt->submitted_at !== null || $attempt->completed_at !== null || ($attempt->answers ?? []) !== [])))) {
            return false;
        }

        return ! $this->mcSelectionReferencedElsewhere($work, $test, null, $lock);
    }

    /** @param Collection<int, mixed> $attempts
     * @param  list<int>  $required
     * @param  list<int>  $inactive
     */
    private function mcSelectionMatchesGroup(Collection $attempts, array $required, array $inactive): bool
    {
        $active = $attempts->filter(fn ($attempt): bool => in_array((int) $attempt->student_id, $required, true))
            ->pluck('student_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        sort($required, SORT_NUMERIC);

        return $active === $required && ! $attempts->contains(fn ($attempt): bool => ! in_array((int) $attempt->student_id, $required, true)
            && ! in_array((int) $attempt->student_id, $inactive, true));
    }

    private function mcSelectionReferencedElsewhere(TeachingCourseWork $work, McTest $test, ?string $slot, bool $lock = false): bool
    {
        foreach ($work->status['multiple_choice_tests'] ?? [] as $key => $binding) {
            if ($key !== $slot && (int) ($binding['test_id'] ?? 0) === (int) $test->id) {
                return true;
            }
        }
        $shared = TeachingCourseWork::query()->whereKeyNot($work->id)
            ->whereHas('teachingCourse', fn ($query) => $query->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('user_id', $work->teachingCourse->user_id))
            ->where(function ($query) use ($test): void {
                $query->whereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((int) $test->id, JSON_THROW_ON_ERROR)])
                    ->orWhereRaw('JSON_CONTAINS(JSON_EXTRACT(status, ?), ?)', ['$.multiple_choice_tests.*.test_id', json_encode((string) $test->id, JSON_THROW_ON_ERROR)]);
            });

        return ($lock ? $shared->lockForUpdate() : $shared)->exists();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function updateMcSelection(TeachingCourseWork $courseWork, User $actor, array $data, ?Closure $onStep = null): array
    {
        if (($data['mc_selection'] ?? null) === 'yes' && isset($data['catalog_id'])) {
            return $this->updateMcCatalogSelection($courseWork, $actor, $data, $onStep);
        }

        return DB::transaction(function () use ($courseWork, $actor, $data): array {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($courseWork->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            $slot = $this->mcSelectionSlot($work, $data['date'] ?? null);
            $status = $work->status ?? [];
            $binding = $status['multiple_choice_tests'][$slot] ?? null;
            abort_if($binding !== null && (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $work->id
                || ! is_numeric($binding['test_id'] ?? null)), 409, 'Die vorhandene MC-Zuordnung ist nicht eindeutig.');
            $currentId = $binding === null ? null : (int) $binding['test_id'];
            $expectedId = $data['expected_test_id'] === null ? null : (int) $data['expected_test_id'];
            abort_unless($currentId === $expectedId, 409, 'Die MC-Zuordnung wurde inzwischen geändert. Bitte den Dialog neu öffnen.');
            $newId = $data['mc_selection'] === 'yes' && $data['test_id'] !== null ? (int) $data['test_id'] : null;
            abort_if($data['mc_selection'] === 'yes' && $newId === null || $data['mc_selection'] === 'no' && $data['test_id'] !== null,
                422, 'Bitte einen vorhandenen MC-Test auswählen oder „Nein“ wählen.');
            $query = McTest::query()->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id);
            $current = $currentId ? (clone $query)->lockForUpdate()->find($currentId) : null;
            abort_if($currentId && ! $current, 409, 'Der zugeordnete MC-Test ist nicht eindeutig zugänglich.');
            if ($newId !== $currentId) {
                $configuration = app(TeachingWorkMultipleChoice::class)->configuration($work, $actor, $data['date'] ?? null, [], true);
                abort_if($configuration['locked'], 409, $configuration['message'] ?? 'Der Bereitstellungsstand konnte nicht bestätigt werden.');
                abort_if(! empty($status['student_distribution']['prepared_at']) || ! empty($status['student_distribution']['published_at']),
                    409, 'Die Aufgabenbereitstellung wurde bereits vorbereitet. Die MC-Zuordnung kann nicht geändert werden.');
                if ($current) {
                    $attempts = $current->attempts()->lockForUpdate()->get();
                    abort_if($current->starts_at !== null || $attempts->contains(fn ($attempt): bool => $attempt->started_at !== null || $attempt->submitted_at !== null || $attempt->completed_at !== null || ($attempt->answers ?? []) !== []),
                        409, 'Der zugeordnete MC-Test wurde bereits begonnen. Seine Zuordnung bleibt zum Schutz der Versuche erhalten.');
                }
                if ($newId !== null) {
                    $test = (clone $query)->lockForUpdate()->find($newId);
                    $required = $this->mcSelectionStudents($work, $slot);
                    $inactive = app(TeachingWorkStudentDistribution::class)->verifiedInactiveMcGroupAccounts($work, $slot, true);
                    abort_unless($test && $this->mcSelectionEligible($work, $test, $required, $inactive, true), 422,
                        'Der Test ist nicht vorbereitet, gehört einer anderen Arbeit oder seine Teilnehmenden passen nicht genau zur Prüfungsgruppe.');
                    $status['multiple_choice_tests'][$slot] = ['work_id' => (int) $work->id, 'test_id' => $newId];
                    $current = $test;
                } else {
                    unset($status['multiple_choice_tests'][$slot]);
                    $current = null;
                }
                $work->status = $status;
                $work->save();
            }

            return ['linked_test_id' => $newId, 'linked_test_title' => $current?->title, 'expected_test_id' => $newId,
                'work' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->load('teachingCourseWorkGroupStudents'))];
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function updateMcCatalogSelection(TeachingCourseWork $courseWork, User $actor, array $data, ?Closure $onStep = null): array
    {
        return DB::transaction(function () use ($courseWork, $actor, $data, $onStep): array {
            $onStep?->__invoke('mc_check');
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($courseWork->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            $slot = $this->mcSelectionSlot($work, $data['date'] ?? null);
            $status = $work->status ?? [];
            $binding = $status['multiple_choice_tests'][$slot] ?? null;
            abort_if($binding !== null && (! is_array($binding) || (int) ($binding['work_id'] ?? 0) !== (int) $work->id
                || ! is_numeric($binding['test_id'] ?? null)), 409, 'Die vorhandene MC-Zuordnung ist nicht eindeutig.');
            $currentId = $binding === null ? null : (int) $binding['test_id'];
            $expectedId = $data['expected_test_id'] === null ? null : (int) $data['expected_test_id'];
            $selectedId = $data['test_id'] === null ? null : (int) $data['test_id'];
            $catalogId = (int) $data['catalog_id'];
            $query = McTest::query()->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $work->teachingCourse->user_id);
            $current = $currentId ? (clone $query)->lockForUpdate()->find($currentId) : null;
            abort_if($currentId && ! $current, 409, 'Der zugeordnete MC-Test ist nicht eindeutig zugänglich.');
            $same = $current && (int) $current->catalog_id === $catalogId && ($selectedId === null || $selectedId === $currentId);
            abort_unless($currentId === $expectedId || ($same && $expectedId === null), 409,
                'Die MC-Zuordnung wurde inzwischen geändert. Bitte den Dialog neu öffnen.');
            if ($same) {
                return ['linked_test_id' => $currentId, 'linked_test_title' => $current->title,
                    'linked_catalog_id' => $catalogId, 'expected_test_id' => $currentId,
                    'action' => 'unchanged', 'test' => $this->mcSelectionTestDetails($current),
                    'work' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->load('teachingCourseWorkGroupStudents'))];
            }
            $addOnly = (bool) ($data['mc_add_only'] ?? false);
            abort_if($addOnly && $currentId !== null, 409, 'Es ist inzwischen ein Multiple-Choice-Test verknüpft.');
            $configuration = app(TeachingWorkMultipleChoice::class)->configuration($work, $actor, $data['date'] ?? null, [], true,
                allowUnreleasedPreparation: $addOnly);
            abort_if($configuration['locked'], 409, $configuration['message'] ?? 'Der Bereitstellungsstand konnte nicht bestätigt werden.');
            if ($current) {
                $attempts = $current->attempts()->lockForUpdate()->get();
                abort_if($current->starts_at !== null || $attempts->contains(fn ($attempt): bool => $attempt->started_at !== null
                    || $attempt->submitted_at !== null || $attempt->completed_at !== null || ($attempt->answers ?? []) !== []),
                    409, 'Der zugeordnete MC-Test wurde bereits begonnen. Seine Zuordnung bleibt zum Schutz der Versuche erhalten.');
            }
            $catalog = McCatalog::query()->where('school_id', $work->teachingCourse->school_id)
                ->where('owner_id', $work->teachingCourse->user_id)->lockForUpdate()->find($catalogId);
            abort_unless($catalog, 404, 'Der gewählte MC-Katalog ist für diesen Kurs nicht verfügbar.');
            $required = $this->mcSelectionStudents($work, $slot, true);
            $inactive = app(TeachingWorkStudentDistribution::class)->verifiedInactiveMcGroupAccounts($work, $slot, true);
            $candidates = $this->mcCatalogCandidates($work, $data['date'] ?? null, $catalogId)['candidates'];
            if ($selectedId === null && count($candidates) > 1) {
                abort(409, 'Mehrere Tests sind dieser Durchführung zuordenbar. Bitte den passenden Test auswählen.');
            }
            $onStep?->__invoke('mc_prepare');
            $selectedId ??= $candidates[0]['id'] ?? null;
            if ($selectedId !== null) {
                abort_unless(collect($candidates)->contains('id', $selectedId), 409,
                    'Der gewählte Test ist dieser Durchführung nicht eindeutig zugeordnet. Bitte erneut prüfen.');
                $test = (clone $query)->lockForUpdate()->find($selectedId);
                abort_unless($test && $this->mcSelectionEligible($work, $test, $required, $inactive, true, allowStarted: true), 409,
                    'Der gewählte Test oder seine Teilnehmer haben sich geändert. Bitte erneut prüfen.');
                $action = 'reused';
            } else {
                if ($work->finish_until_date === null) {
                    throw ValidationException::withMessages(['finish_until_date' => 'Bitte zuerst das Enddatum der Arbeit festlegen. Ohne Uhrzeit gilt 23:59 Uhr.']);
                }
                $deadline = $work->finalDeadlineInstant();
                if (! $deadline?->isFuture()) {
                    throw ValidationException::withMessages(['finish_until_date' => 'Die Abgabefrist muss für den individuellen MC-Test in der Zukunft liegen.']);
                }
                $settings = $this->validateNewMcCatalogTestSettings($data, $catalog);
                $test = McTest::query()->create(['school_id' => $work->teachingCourse->school_id,
                    'schoolyear_id' => $work->teachingCourse->schoolyear_id, 'owner_id' => $work->teachingCourse->user_id,
                    'catalog_id' => $catalogId, 'title' => app(TeachingWorkMultipleChoice::class)->newTestTitle($work, $data['date'] ?? null),
                    'status' => 'draft', 'question_count' => $settings['question_count'], 'total_points' => $settings['total_points'],
                    'duration_seconds' => (int) $settings['duration_minutes'] * 60,
                    'lead_seconds' => 30, 'start_mode' => 'individual', 'closes_at' => $deadline,
                    'exam_mode' => false, 'balance_types' => true, 'shuffle_questions' => true, 'shuffle_options' => true]);
                $test = app(McTestService::class)->prepare($test, $required, true, $work, $slot);
                $snapshot = $test->snapshot;
                $snapshot['schooltool_work'] = ['work_id' => (int) $work->id, 'course_id' => (int) $work->teaching_course_id, 'slot' => $slot];
                $snapshot['work_deadline_inherited'] = true;
                $test->forceFill(['snapshot' => $snapshot])->save();
                $action = 'created';
            }
            $onStep?->__invoke('mc_assign');
            $status['multiple_choice_tests'][$slot] = ['work_id' => (int) $work->id, 'test_id' => $test->id];
            $work->status = $status;
            $work->save();

            return ['linked_test_id' => $test->id, 'linked_test_title' => $test->title, 'linked_catalog_id' => $catalogId,
                'expected_test_id' => $test->id, 'action' => $action, 'test' => $this->mcSelectionTestDetails($test),
                'work' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->load('teachingCourseWorkGroupStudents'))];
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function streamMcSelection(Request $request, TeachingCourseWork $work, User $actor, array $data): StreamedResponse
    {
        $stream = app(TeachingWorkRefreshProgress::class);

        return $stream->stream($request, function () use ($stream, $work, $actor, $data): JsonResponse {
            $progress = ['state' => 'running', 'active' => null, 'completed' => [], 'skipped' => [],
                'steps' => ['mc_check', 'mc_prepare', 'mc_assign']];
            $onStep = function (string $step) use ($stream, &$progress): void {
                if ($step === 'mc_check') {
                    $progress['completed'] = [];
                } elseif ($progress['active'] !== null) {
                    $progress['completed'][] = $progress['active'];
                }
                $progress['active'] = $step;
                $stream->save(null, $progress);
            };
            try {
                $result = $this->updateMcSelection($work, $actor, $data, $onStep);
                if ($progress['active'] !== null) {
                    $progress['completed'][] = $progress['active'];
                }
                $progress['state'] = 'done';
                $progress['active'] = null;
                $progress['skipped'] = array_values(array_diff($progress['steps'], $progress['completed']));
                $stream->save(null, $progress);

                return response()->json($result);
            } catch (Throwable $exception) {
                $progress['state'] = 'failed';
                $stream->save(null, $progress);
                throw $exception;
            }
        });
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateNewMcCatalogTestSettings(array $data, McCatalog $catalog): array
    {
        $defaults = $this->mcSelectionDefaults();
        $settings = Validator::make(array_intersect_key($data, $defaults) + $defaults, [
            'question_count' => ['required', 'integer', 'min:1', 'max:2000'],
            'total_points' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ], [
            'question_count.*' => 'Bitte eine ganze Fragenanzahl zwischen 1 und 2.000 eingeben.',
            'total_points.*' => 'Bitte Testpunkte zwischen 0,01 und 100.000 mit höchstens zwei Nachkommastellen eingeben.',
            'duration_minutes.*' => 'Bitte eine ganze Bearbeitungszeit zwischen 1 und 1.440 Minuten eingeben.',
        ])->validate();
        $available = count($catalog->questionsForTesting());
        if ($settings['question_count'] > $available) {
            throw ValidationException::withMessages(['question_count' => "Es sind zu wenige aktive, geprüfte Fragen vorhanden (verfügbar: {$available})."]);
        }

        return $settings;
    }

    public function prepareExercise(Request $request, TeachingCourseWork $course_work, TeachingWorkAiExport $export, TeachingWorkMultipleChoice $multipleChoice, TeachingWorkExerciseGeneration $generation, TeachingDirectAssessment $direct): JsonResponse|StreamedResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        if ($request->has('mc_selection')) {
            $data = $request->validate(['mc_selection' => ['required', Rule::in(['yes', 'no'])],
                'date' => ['nullable', 'date_format:Y-m-d'], 'test_id' => ['present', 'nullable', 'integer', 'min:1'],
                'expected_test_id' => ['present', 'nullable', 'integer', 'min:1'],
                'catalog_id' => ['nullable', 'integer', 'min:1'], 'mc_add_only' => ['sometimes', 'boolean']]);
            $data += $request->only(array_keys($this->mcSelectionDefaults()));

            if ($request->boolean('mc_add_only') && $request->boolean('stream_progress')
                && $data['mc_selection'] === 'yes' && isset($data['catalog_id'])) {
                return $this->streamMcSelection($request, $course_work, $actor, $data);
            }

            return response()->json($this->updateMcSelection($course_work, $actor, $data))
                ->header('Cache-Control', 'private, no-store');
        }
        if ($request->input('execution') === 'direct') {
            $data = $request->validate(['execution' => ['required', 'in:direct'], 'date' => ['nullable', 'date_format:Y-m-d'],
                'template_points_snapshot' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
                'template_points_target' => ['nullable', 'numeric', 'min:0', 'max:100000', 'multiple_of:0.5'],
                'preview_mode' => ['sometimes', 'in:summary'],
                'expected_test_id' => ['present', 'nullable', 'integer', 'min:1']]);
            abort_if(isset($data['template_points_target']) && (float) $data['template_points_target'] !== (float) $data['template_points_snapshot'],
                422, 'Eine abweichende Punkteverteilung kann derzeit nicht automatisch erstellt werden. Bitte die Vorlagenpunkte unverändert verwenden.');

            if ($request->boolean('stream_progress') && ! $request->attributes->get('direct_preview_stream')) {
                $request->attributes->set('direct_preview_stream', true);

                return app(TeachingWorkRefreshProgress::class)->stream($request,
                    fn (): JsonResponse => $this->prepareExercise($request, $course_work, $export, $multipleChoice, $generation, $direct));
            }
            if ($request->attributes->get('direct_preview_stream')) {
                $service = app(TeachingWorkRefreshProgress::class);
                $progress = ['state' => 'running', 'active' => 'assessment_candidates', 'completed' => [], 'skipped' => [],
                    'steps' => ['assessment_candidates', 'assessment_assignment', 'assessment_criteria', 'assessment_target']];
                $service->save(null, $progress);
                $report = function (string $event, ?string $step = null) use ($service, &$progress): void {
                    if ($event === 'start') {
                        $progress['active'] = $step;
                    } elseif ($event === 'completed') {
                        $progress['completed'][] = $step;
                        $progress['active'] = null;
                    }
                    $service->save(null, $progress);
                };
                try {
                    $result = $direct->preview($course_work, $actor, $data['date'] ?? null,
                        (float) $data['template_points_snapshot'], isset($data['expected_test_id']) ? (int) $data['expected_test_id'] : null,
                        ($data['preview_mode'] ?? null) === 'summary', $report);
                    $progress['state'] = 'done';
                    $progress['active'] = null;
                    $service->save(null, $progress);

                    return response()->json(['generation' => $result])->header('Cache-Control', 'private, no-store');
                } catch (Throwable $exception) {
                    $progress['state'] = 'failed';
                    $service->save(null, $progress);
                    throw $exception;
                }
            }

            return response()->json(['generation' => $direct->preview($course_work, $actor, $data['date'] ?? null,
                (float) $data['template_points_snapshot'], isset($data['expected_test_id']) ? (int) $data['expected_test_id'] : null,
                ($data['preview_mode'] ?? null) === 'summary')])
                ->header('Cache-Control', 'private, no-store');
        }
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'], 'mc_mode' => ['required_unless:execution,openai', 'nullable', 'in:existing,module'],
            'execution' => ['sometimes', 'in:openai'],
            'email_form_policy' => ['required_if:execution,openai', 'nullable', 'string', 'min:5', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:8000'],
            'test_id' => ['required_if:mc_mode,existing', 'nullable', 'integer', 'min:1'],
            'catalog_id' => ['required_if:mc_mode,new', 'nullable', 'integer', 'min:1'],
            'question_count' => ['required_if:mc_mode,module', 'integer', 'min:1', 'max:2000'],
            'total_points' => ['required_if:mc_mode,new', 'nullable', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'duration_minutes' => ['required_if:mc_mode,module', 'nullable', 'integer', 'min:1', 'max:1440'],
            'catalog_fingerprint' => ['required_if:mc_mode,module', 'nullable', 'string', 'size:64'],
            'template_points_snapshot' => ['nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'mc_points' => ['required_with:mc_mode', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'other_points' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'expected_maximum_points' => ['required_with:mc_mode', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'request_id' => ['required', 'uuid'], 'expected_test_id' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        if (($data['execution'] ?? null) === 'openai') {
            $generation->requireConfigured();
        }
        $prepared = DB::transaction(function () use ($course_work, $actor, $data, $export, $multipleChoice): array {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            $preview = $export->buildExercise($work, $actor, false, $data['date'] ?? null);
            if (empty($data['mc_mode'])) {
                return $preview;
            }
            $modules = app(DropboxModuleLinks::class);
            $link = $modules->current($work);
            $connection = $link ? DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')
                ->find($link->dropbox_connection_id) : null;
            abort_unless($link && $connection && $link->account_id === $connection->account_id, 422,
                'Der bisherige MC-Ablauf ist nur für einen online bestätigten alten Prüfungsordner verfügbar.');
            $legacyExercise = $modules->exercise($connection, $work, $link, $data['date'] ?? null, refresh: true, initial: false);
            abort_unless(($legacyExercise['status'] ?? null) === 'found' && array_key_exists('preparation', $legacyExercise)
                && $legacyExercise['preparation'] === null && is_string($legacyExercise['folder']['id'] ?? null)
                && $legacyExercise['folder']['id'] !== '', 422,
                'Der bisherige MC-Ablauf ist nur für einen online bestätigten alten Prüfungsordner verfügbar.');
            $this->assessmentSource($work, $actor, $data['template_points_snapshot'] ?? null);
            $catalog = null;
            if ($data['mc_mode'] === 'existing') {
                $linkedTest = $multipleChoice->linkedModuleTest($work, $actor, $data['date'] ?? null, true);
                abort_unless($linkedTest && (int) $linkedTest->id === (int) ($data['test_id'] ?? 0), 409,
                    'Für diese Arbeit ist kein passender MC-Test aus dem verknüpften Lernmodul bestätigt.');
            } else {
                $link = app(DropboxModuleLinks::class)->current($work);
                abort_unless($link, 409, 'Das Lernmodul ist nicht mehr bestätigt.');
                $catalog = app(McModuleTemplateSource::class)->workCatalog($actor, $link);
                abort_unless($catalog && hash_equals($catalog['fingerprint'], $data['catalog_fingerprint'])
                    && $data['question_count'] <= count($catalog['questions']), 409,
                    'Der Fragenkatalog hat sich geändert oder ist nicht eindeutig. Bitte erneut prüfen.');
            }
            $mcData = array_diff_key($data, array_flip(['execution', 'email_form_policy', 'instructions']));
            $test = $multipleChoice->prepare($work, $actor, $mcData, $preview['context']['course_roster']['participants'], $preview['context']['work'], $catalog);

            return $export->buildLegacyExercise($work, $actor, $data['date'] ?? null, $test, $legacyExercise['folder']);
        });
        $result = ['data' => $prepared];
        if (($data['execution'] ?? null) === 'openai') {
            $result['generation'] = $generation->preview($course_work->fresh('teachingCourse'), $actor, $prepared,
                $data['email_form_policy'], $data['instructions'] ?? '');
        }

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function storeExercise(Request $request, TeachingCourseWork $course_work, TeachingWorkExerciseGeneration $generation, TeachingAssessmentRuns $runs): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate(['generation_id' => ['required', 'uuid'], 'approved' => ['required', 'accepted'], 'kind' => ['sometimes', 'in:direct']]);

        if (($data['kind'] ?? null) === 'direct') {
            return response()->json(['run' => $runs->start($course_work, $actor, $data['generation_id'], [
                'generation_id' => $data['generation_id'], 'date' => null,
            ])], 202)->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['data' => $generation->store($course_work, $actor, $data['generation_id'])])
            ->header('Cache-Control', 'private, no-store');
    }

    public function aiExport(Request $request, TeachingCourseWork $course_work, TeachingWorkAiExport $export): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor, 403);
        $data = $request->validate(['purpose' => ['sometimes', 'in:exercise,email_preparation,email_dispatch,submission_collection,evaluation'],
            'include_mc' => ['nullable', 'boolean'],
            'address_form' => ['required_if:purpose,email_preparation', 'nullable', Rule::in(['du', 'sie'])],
            'delivery_mode' => ['required_if:purpose,email_dispatch', 'nullable', Rule::in(['live', 'test'])],
            'collection_mode' => ['required_if:purpose,submission_collection', 'nullable', Rule::in(['collect', 'repeat'])],
            'evaluation_mode' => ['required_if:purpose,evaluation', 'nullable', Rule::in(['grade', 'repeat'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'template_points_snapshot' => ['required_with:template_points_target', 'nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'template_points_target' => ['nullable', 'numeric', 'min:0', 'max:100000', 'multiple_of:0.5']]);

        return DB::transaction(function () use ($course_work, $actor, $export, $data): JsonResponse {
            $work = TeachingCourseWork::query()->with('teachingCourse')->findOrFail($course_work->id);
            abort_unless($work->teachingCourse, 403);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);

            $exercise = ($data['purpose'] ?? null) === 'exercise';
            abort_unless($exercise || $work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');

            if (($data['purpose'] ?? null) === 'evaluation') {
                return response()->json(['data' => $export->buildEvaluation($work, $actor, $data['evaluation_mode'] === 'repeat', $data['date'] ?? null)])
                    ->header('Cache-Control', 'private, no-store');
            }

            if (($data['purpose'] ?? null) === 'submission_collection') {
                return response()->json(['data' => $export->buildSubmissionCollection($work, $actor, $data['collection_mode'] === 'repeat', $data['date'] ?? null)])
                    ->header('Cache-Control', 'private, no-store');
            }

            if (($data['purpose'] ?? null) === 'email_preparation') {
                return response()->json(['data' => $export->buildEmailPreparation($work, $actor, $data['address_form'], $data['date'] ?? null)])
                    ->header('Cache-Control', 'private, no-store');
            }

            if (($data['purpose'] ?? null) === 'email_dispatch') {
                return response()->json(['data' => $export->buildEmailDispatch($work, $actor, $data['delivery_mode'], $data['date'] ?? null)])
                    ->header('Cache-Control', 'private, no-store');
            }

            $assessment = $exercise ? $this->assessmentSource($work, $actor, $data['template_points_snapshot'] ?? null) : null;
            if ($exercise && isset($data['template_points_target'])) {
                $assessment['target'] = (float) $data['template_points_target'];
            }

            return response()->json(['data' => $exercise ? $export->buildExercise($work, $actor, false, $data['date'] ?? null, null, $assessment)
                : $export->build($work, $actor)])
                ->header('Cache-Control', 'private, no-store');
        });
    }

    /** @return array{verified: bool, points: ?float} */
    private function assessmentSource(TeachingCourseWork $work, User $actor, mixed $expectedPoints = null): array
    {
        $link = app(DropboxModuleLinks::class)->current($work);
        $result = ['verified' => false, 'points' => null];
        try {
            if ($link) {
                $result = ['verified' => true, 'points' => app(McModuleTemplateSource::class)->assessmentPoints($actor, $link)];
            }
        } catch (Throwable) {
        }
        abort_if($expectedPoints !== null && ($result['points'] === null
            || round((float) $expectedPoints, 2) !== round($result['points'], 2)), 409,
            'Die Punkte der Prüfungsvorlage konnten nicht erneut bestätigt werden oder haben sich geändert. Bitte den Dialog erneut öffnen.');

        return $result;
    }

    public function importJson(Request $request, TeachingCourseWork $course_work, TeachingWorkJsonImport $importer, TeachingWorkFolderImport $folderImporter, TeachingWorkDispatchImport $dispatchImporter): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        abort_if((int) $request->server('CONTENT_LENGTH', 0) > 6 * 1024 * 1024, 422, 'Upload einschließlich Transportverpackung überschreitet 6 MiB.');
        $data = $request->validate([
            'package' => ['required_without:documents', 'file', 'extensions:json', 'max:256'],
            'identity_export' => ['sometimes', 'file', 'extensions:json', 'max:1024'],
            'pdfs' => ['sometimes', 'array', 'max:20'],
            'pdfs.*' => ['file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:6144'],
            'folder' => ['required_with:documents', 'nullable', 'string', 'max:255'],
            'documents' => ['sometimes', 'string', 'max:6291456'],
            'apply' => ['sometimes', 'boolean'],
            'hash' => ['required_if:apply,1', 'nullable', 'string', 'size:64'],
        ]);
        $uploads = $request->file('pdfs', []);
        $text = $request->file('package')?->get() ?? '';
        $identityExportText = $request->file('identity_export')?->get();
        abort_if($identityExportText !== null && ! $request->hasFile('package'), 422, 'Arbeitsdatenexport benötigt das unveränderte Bewertungspaket.');
        $payloadSize = strlen($text) + strlen($identityExportText ?? '') + strlen($data['documents'] ?? '') + array_sum(array_map(fn ($file): int => $file->getSize(), $uploads));
        $transportSize = (int) $request->server('CONTENT_LENGTH', 0);
        if ($transportSize === 0) {
            $transportSize = $payloadSize + strlen(http_build_query($request->except(['package', 'pdfs', 'identity_export']))) + (count($uploads) + 4) * 256;
        }
        abort_if(max($transportSize, $payloadSize, strlen($request->getContent())) > 6 * 1024 * 1024, 422, 'Upload einschließlich Transportverpackung überschreitet 6 MiB.');
        $bundle = $request->hasFile('package') ? $importer->parse($text, $uploads, $identityExportText) : null;
        $dispatchBundle = isset($data['documents']) ? $folderImporter->parse($data['folder'], $data['documents'], '[]', []) : null;
        if ($dispatchBundle !== null && $dispatchBundle['evaluation'] !== null) {
            throw ValidationException::withMessages(['documents' => 'Im JSON-Import sind nur Bereitstellungs-, Vorbereitungs- und Versandbelege als zusätzliche Textdateien erlaubt.']);
        }
        if ($bundle === null && ($uploads !== [] || (($dispatchBundle['protocols'] ?? []) === [] && ($dispatchBundle['provisions'] ?? []) === [] && ($dispatchBundle['deadline'] ?? null) === null))) {
            throw ValidationException::withMessages(['documents' => 'Ohne Bewertungsdatei mindestens eine Bereitstellung, Vorbereitung oder ein Versandprotokoll auswählen; Bewertungs-PDFs benötigen ihr JSON-Paket.']);
        }
        $createdPaths = [];
        try {
            $result = DB::transaction(function () use ($course_work, $actor, $importer, $folderImporter, $bundle, $dispatchBundle, $uploads, $data, &$createdPaths): array {
                $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                $work->setRelation('teachingCourse', TeachingCourse::query()->lockForUpdate()->findOrFail($work->teaching_course_id));
                $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
                abort_unless($work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
                $enrollments = $work->teachingCourse->teachingCourseStudents()->lockForUpdate()->get();
                $users = User::query()->whereIn('id', $enrollments->pluck('user_id'))->lockForUpdate()->get();
                Import116::query()->whereIn('id', $enrollments->pluck('import116_id')->merge($users->pluck('import116_id'))->filter())->lockForUpdate()->get();
                $work->teachingCourseWorkGroupStudents()->lockForUpdate()->get();
                $identityBinding = isset($bundle['identity_export']) ? $importer->identityBinding($work, $this->teachingCourseActor($actor, $work->teachingCourse), $bundle) : null;
                $dispatchPreviews = $dispatchBundle !== null ? $folderImporter->previewDispatches($work, $dispatchBundle, $identityBinding) : [];
                $provisionPreviews = $dispatchBundle !== null ? $folderImporter->previewProvisions($work, $dispatchBundle, $identityBinding) : [];
                $sourceSnapshot = $work->getAttributes();
                $deadlineFields = $folderImporter->deadlineFields($work, $dispatchBundle ?? []);
                $work->forceFill($deadlineFields);
                $preview = $bundle !== null ? $importer->preview($work, $this->teachingCourseActor($actor, $work->teachingCourse), $bundle,
                    $folderImporter->availabilitySources($work, $provisionPreviews, $dispatchPreviews)) : [
                        'target_work' => ['title' => $work->title, 'course_title' => $work->teachingCourse->title],
                        'exercise' => null, 'rows' => [], 'can_import' => true, 'assessment_included' => false,
                        'hash' => hash('sha256', json_encode($work->getAttributes(), JSON_THROW_ON_ERROR)),
                    ];
                $preview['dispatches'] = [];
                foreach ($dispatchPreviews as $dispatchPreview) {
                    $preview['dispatches'][] = $dispatchPreview;
                    $preview['can_import'] = $preview['can_import'] && $dispatchPreview['can_import'];
                }
                $preview['provisions'] = $provisionPreviews;
                $preview['deadline_update'] = $deadlineFields === [] ? null : ($dispatchBundle['deadline'] ?? null);
                foreach ($provisionPreviews as $provisionPreview) {
                    $preview['can_import'] = $preview['can_import'] && $provisionPreview['can_import'];
                    if ($bundle !== null) {
                        abort_unless($provisionPreview['exercise_id'] === $bundle['package']['exercise_id'], 422, 'Bereitstellung und Bewertung gehören zu verschiedenen Aufgaben.');
                    }
                }
                if ($dispatchBundle !== null) {
                    $preview['hash'] = hash('sha256', json_encode([$preview['hash'], $sourceSnapshot, $dispatchBundle['sources'], $preview['dispatches'], $preview['provisions']], JSON_THROW_ON_ERROR));
                }
                if (! ($data['apply'] ?? false)) {
                    return ['preview' => $preview];
                }
                abort_unless($preview['can_import'], 422, $preview['deadline_context']['requires_review'] ?? false
                    ? $preview['deadline_context']['message'] : 'JSON-Paket enthält ungeklärte Zuordnungen.');
                abort_unless(hash_equals($preview['hash'], $data['hash'] ?? ''), 409, 'Arbeit, Teilnehmer oder Paket geändert. Bitte Vorschau erneut laden.');
                $messages = [];
                if ($dispatchBundle !== null && ($dispatchBundle['protocols'] !== [] || $dispatchBundle['provisions'] !== [] || ($dispatchBundle['deadline'] ?? null) !== null)) {
                    $messages = $folderImporter->apply($work, $this->teachingCourseActor($actor, $work->teachingCourse), $dispatchBundle, $createdPaths, $identityBinding)['messages'];
                }
                if ($bundle !== null) {
                    $importer->apply($work, $bundle, $preview, $uploads, $createdPaths);
                }

                return ['data' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->fresh()), 'messages' => $messages];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($createdPaths);
            throw $exception;
        }

        return response()->json($result);
    }

    public function importFolder(Request $request, TeachingCourseWork $course_work, TeachingWorkFolderImport $importer): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $data = $request->validate([
            'folder' => ['required', 'string', 'max:255'],
            'documents' => ['required', 'string', 'max:6291456'],
            'pdf_paths' => ['required', 'string', 'max:32768'],
            'pdfs' => ['sometimes', 'array', 'max:20'],
            'pdfs.*' => ['file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:4096'],
        ]);
        $bundle = $importer->parse($data['folder'], $data['documents'], $data['pdf_paths'], $request->file('pdfs', []));
        $createdPaths = [];
        try {
            $result = DB::transaction(function () use ($course_work, $actor, $importer, $bundle, &$createdPaths): array {
                $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                $work->setRelation('teachingCourse', TeachingCourse::query()->lockForUpdate()->findOrFail($work->teaching_course_id));
                $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
                abort_unless($work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
                try {
                    $summary = $importer->apply($work, $this->teachingCourseActor($actor, $work->teachingCourse), $bundle, $createdPaths);
                } catch (Throwable $exception) {
                    Storage::disk('local')->delete($createdPaths);
                    $createdPaths = [];
                    throw $exception;
                }

                return ['summary' => $summary, 'data' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->fresh())];
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($createdPaths);
            throw $exception;
        }

        return response()->json($result);
    }

    public function importDispatch(Request $request, TeachingCourseWork $course_work, TeachingWorkDispatchImport $importer): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $data = $request->validate([
            'protocol' => ['required', 'file', 'extensions:txt', 'mimetypes:text/plain,application/json', 'max:1024'],
            'purpose' => ['sometimes', Rule::in(['tasks', 'results'])],
            'apply' => ['sometimes', 'boolean'],
            'hash' => ['required_if:apply,1', 'nullable', 'string', 'size:64'],
        ]);
        $upload = $request->file('protocol');
        $report = $importer->parse($upload->get());
        if ($report['purpose'] !== ($data['purpose'] ?? 'results')) {
            throw ValidationException::withMessages(['protocol' => 'Das Protokoll gehört zum anderen Versandvorgang. Bitte den passenden Importeinstieg auswählen.']);
        }
        $sha256 = hash_file('sha256', $upload->getRealPath());
        $name = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
        $createdPath = null;
        try {
            $result = DB::transaction(function () use ($course_work, $actor, $importer, $report, $sha256, $name, $upload, $data, &$createdPath): array {
                $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
                abort_unless($work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
                $preview = $importer->preview($work, $report, $sha256);
                if (! ($data['apply'] ?? false)) {
                    return ['preview' => $preview];
                }
                abort_unless($preview['can_import'], 422, 'Versandprotokoll enthält ungeklärte Zuordnungen.');
                abort_unless(hash_equals($preview['hash'], $data['hash'] ?? ''), 409, 'Arbeit, Kurs oder Protokoll wurde geändert. Bitte Vorschau erneut laden.');
                $importer->applyParticipantAdditions($work, $preview['participant_additions']);
                $preview = $importer->preview($work, $report, $sha256);
                abort_unless($preview['can_import'], 422, 'Versandprotokoll enthält ungeklärte Zuordnungen.');
                $directory = "teaching/work_dispatches/{$work->teachingCourse->school_id}/{$work->id}";
                $path = "{$directory}/{$sha256}.txt";
                if (! Storage::disk('local')->exists($path)) {
                    $createdPath = $path;
                    if ($upload->storeAs($directory, "{$sha256}.txt", 'local') !== $path) {
                        throw ValidationException::withMessages(['protocol' => 'Versandprotokoll konnte nicht gespeichert werden.']);
                    }
                }
                $importer->apply($work, $preview, $sha256, $name, $path);

                return ['data' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->fresh())];
            });
        } catch (Throwable $exception) {
            if ($createdPath !== null) {
                Storage::disk('local')->delete($createdPath);
            }
            throw $exception;
        }

        return response()->json($result);
    }

    public function downloadDispatch(TeachingCourseWork $course_work, string $sha256, TeachingWorkDispatchImport $importer): StreamedResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);

        return $importer->streamLog($course_work, $sha256);
    }

    public function importEvaluations(Request $request, TeachingCourseWork $course_work, TeachingWorkMarkdownImport $importer): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $data = $request->validate([
            'reports' => ['required', 'string', 'max:1048576'],
            'pdfs' => ['sometimes', 'array', 'max:20'],
            'pdfs.*' => ['file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:4096'],
            'apply' => ['sometimes', 'boolean'], 'hash' => ['required_if:apply,1', 'nullable', 'string', 'size:64'],
        ]);
        $documents = json_decode($data['reports'], true);
        if (! is_array($documents) || count($documents) > 100) {
            throw ValidationException::withMessages(['reports' => 'Ungültige oder zu große Berichtsdateiauswahl.']);
        }
        $files = [];
        foreach ($documents as $document) {
            if (! is_array($document) || ! is_string($document['name'] ?? null) || ! is_string($document['text'] ?? null) ||
                basename(str_replace('\\', '/', $document['name'])) !== $document['name'] || strtolower(pathinfo($document['name'], PATHINFO_EXTENSION)) !== 'md' ||
                strlen($document['text']) > 262144 || isset($files[$document['name']])) {
                throw ValidationException::withMessages(['reports' => 'Ungültige oder doppelte Markdown-Datei.']);
            }
            $files[$document['name']] = $document['text'];
        }
        $report = $importer->parse($files);
        $uploads = $request->file('pdfs', []);
        abort_if(array_sum(array_map(fn ($file): int => $file->getSize(), $uploads)) > 6 * 1024 * 1024, 422, 'PDF-Auswahl überschreitet 6 MB.');
        $pdfs = array_map(fn ($file): array => ['name' => basename(str_replace('\\', '/', $file->getClientOriginalName())), 'sha256' => hash_file('sha256', $file->getRealPath())], $uploads);
        $createdPaths = [];
        try {
            $result = DB::transaction(function () use ($course_work, $actor, $importer, $report, $pdfs, $uploads, $data, &$createdPaths): array {
                $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
                abort_unless($work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
                $preview = $importer->preview($work, $this->teachingCourseActor($actor, $work->teachingCourse), $report, $pdfs);
                if (! ($data['apply'] ?? false)) {
                    return ['preview' => $preview];
                }
                abort_unless($preview['can_import'], 422, 'Keine eindeutigen importierbaren Daten.');
                abort_unless(hash_equals($preview['hash'], $data['hash'] ?? ''), 409, 'Arbeit oder Auswertung wurde geändert. Bitte Vorschau erneut laden.');
                $importer->apply($work, $preview);
                $importer->storePdfs($work, $preview, $uploads, $pdfs, $createdPaths);

                return ['data' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->fresh())];
            });
        } catch (Throwable $exception) {
            if ($createdPaths !== []) {
                Storage::disk('local')->delete($createdPaths);
            }
            throw $exception;
        }

        return response()->json($result);
    }

    public function downloadEvaluation(Request $request, TeachingCourseWork $course_work, string $sha256): StreamedResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);

        return app(TeachingWorkMarkdownImport::class)->streamPdf($course_work, $sha256, inline: $request->boolean('inline'));
    }

    public function index(Request $request, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $courseId = $request->query('course_id');

        if (! $courseId) {
            return response()->json(['data' => []]);
        }

        $course = TeachingCourse::findOrFail($courseId);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $works = $course->teachingCourseWorks()
            ->with('teachingCourseWorkGroupStudents')
            ->orderBy('date_for_all_groups', 'desc')
            ->get();

        return response()->json(['data' => $entrySyncService->serializeWorks($works, $course)]);
    }

    public function store(
        Request $request,
        TeachingCourseWorkService $workService,
        TeachingCourseStudentEntryService $entryService,
        TeachingCourseWorkEntrySyncService $entrySyncService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = TeachingCourse::findOrFail($request->input('teaching_course_id'));
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $request->validate(['creation_request_id' => ['sometimes', 'uuid']]);
        $creationRequestId = $request->input('creation_request_id');
        $creationRequestHash = $creationRequestId === null ? null : hash('sha256', json_encode([
            'user_id' => $auth_user->id,
            'payload' => $request->except('creation_request_id'),
        ], JSON_THROW_ON_ERROR));

        $allowedTypes = $entryService->allowedGradingTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $gradeRules = $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type'));
        $maximumPlus = $this->validatedMaximumPlus($request, $course, $this->teachingCourseActor($auth_user, $course), $entryService);
        $this->appendMaximumPlusGradeRule($gradeRules, $maximumPlus);
        $validated = $request->validate($this->workValidationRules($typeRules, true, $course, $gradeRules));
        if (isset($validated['status'])) {
            unset($validated['status']['creation_receipt']);
            unset($validated['status']['submission_checks']);
            unset($validated['status']['communicated_deadline_at']);
            unset($validated['status']['communicated_deadline_date']);
            unset($validated['status']['completion_override'], $validated['status']['multiple_choice_tests'], $validated['status']['student_distribution'], $validated['status']['student_task_files'], $validated['status']['deletion_receipt']);
            foreach ([...self::ManualCompletionFields, ...TeachingCourseWork::ManagedWorkflowFields] as $field) {
                unset($validated['status'][$field]);
            }
            unset($validated['status']['provision_events'], $validated['status']['provision_imports']);
            unset($validated['status']['evaluation_pdfs'], $validated['status']['dispatch_logs'], $validated['status']['dispatch_notifications'], $validated['status']['dispatch_attempts'], $validated['status']['folder_import_sources'], $validated['status']['folder_imported_at'], $validated['status']['assessment_json_imports'], $validated['status']['assessment_json_packages']);
        }
        $validated['maximum_plus'] = $maximumPlus;
        $validated['finish_until_date'] ??= $validated['date_for_all_groups'] ?? null;
        $validated['status']['automation_enabled'] = (bool) ($validated['automation_enabled'] ?? true);
        $validated['status']['grade_visibility'] = ['visible' => false];
        unset($validated['automation_enabled']);

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id);
        app(TeachingWorkDeadlineSync::class)->validate(new TeachingCourseWork($validated));

        $work = DB::transaction(function () use ($course, $auth_user, $validated, $entrySyncService, $creationRequestId, $creationRequestHash): TeachingCourseWork {
            if ($creationRequestId !== null) {
                TeachingCourse::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
                $existing = TeachingCourseWork::query()->where('teaching_course_id', $course->id)
                    ->where('status->creation_receipt->id', $creationRequestId)->first();
                if ($existing) {
                    abort_unless(($existing->status['creation_receipt']['user_id'] ?? null) === $auth_user->id
                        && hash_equals($existing->status['creation_receipt']['hash'] ?? '', $creationRequestHash),
                        409, 'Dieser Anlageversuch gehört zu anderen Angaben. Bitte einen neuen Vorgang beginnen.');

                    return $existing;
                }
                $validated['status']['creation_receipt'] = ['id' => $creationRequestId, 'user_id' => $auth_user->id, 'hash' => $creationRequestHash];
            }
            $work = TeachingCourseWork::create($validated);
            $entrySyncService->syncWork($work);

            return $work;
        });

        return response()->json(['data' => $entrySyncService->serializeWork($work->fresh('teachingCourseWorkGroupStudents'))], 201);
    }

    public function show(TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $progress = null;
        $progressKey = null;
        $progressService = app(TeachingWorkRefreshProgress::class);
        if (request()->boolean('stream_progress') && request()->boolean('refresh_collection')) {
            request()->merge(['stream_progress' => false]);

            return $progressService->stream(request(), fn () => $this->show($course_work, $entrySyncService));
        }
        if (request()->boolean('refresh_collection')) {
            $data = request()->validate(['progress_id' => ['sometimes', 'uuid']]);
            if (isset($data['progress_id'])) {
                $progressKey = $progressService->key($auth_user, $course_work, $data['progress_id']);
                $progress = ['state' => 'running', 'active' => 'connection', 'completed' => [], 'skipped' => [],
                    'steps' => ['connection', 'folder', 'collection']];
                $progressService->save($progressKey, $progress);
            }
            try {
                $update = $progressKey === null ? null : function (string $event) use (&$progress, $progressService, $progressKey): void {
                    if ($event === 'unavailable') {
                        $progress['skipped'] = ['connection', 'folder', 'collection'];
                        $progress['active'] = null;
                    } elseif ($event === 'no_folder') {
                        $progress['skipped'][] = 'collection';
                        $progress['active'] = null;
                    } else {
                        $progress['completed'][] = $event;
                        $progress['active'] = match ($event) {
                            'connection' => 'folder',
                            'folder' => 'collection',
                            default => null,
                        };
                    }
                    $progressService->save($progressKey, $progress);
                };
                app(TeachingWorkCollectionStatus::class)->refresh($course_work, $auth_user, onProgress: $update);
                $body = ['data' => $entrySyncService->serializeWork($course_work->load('teachingCourseWorkGroupStudents'))];
                if ($progress !== null) {
                    $progress['state'] = 'done';
                    $progressService->save($progressKey, $progress);
                }
            } catch (Throwable $exception) {
                if ($progress !== null) {
                    $progress['state'] = 'failed';
                    $progressService->save($progressKey, $progress);
                }
                throw $exception;
            }
        }

        $body ??= ['data' => $entrySyncService->serializeWork($course_work->load('teachingCourseWorkGroupStudents'))];
        if ($progress !== null) {
            $body['progress'] = $progress;
        }

        return response()->json($body);
    }

    public function update(
        Request $request,
        TeachingCourseWork $course_work,
        TeachingCourseWorkService $workService,
        TeachingCourseStudentEntryService $entryService,
        TeachingCourseWorkEntrySyncService $entrySyncService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $allowedTypes = $entryService->allowedGradingTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $gradeRules = $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type', $course_work->type));
        $maximumPlus = $this->validatedMaximumPlus($request, $course, $this->teachingCourseActor($auth_user, $course), $entryService, $course_work);
        $this->appendMaximumPlusGradeRule($gradeRules, $maximumPlus);
        $rules = $this->workValidationRules($typeRules, false, $course, $gradeRules);
        $rules = $this->preserveStoredFeedbackRules($request, $course_work, $rules);
        $titleOnly = count($request->all()) === 1 && $request->exists('title');
        $validated = $request->validate($rules, [
            'groups.*.comment.max' => 'Neue oder geänderte Rückmeldungen dürfen höchstens 2048 Zeichen enthalten.',
            'groups.*.comments.*.comment.max' => 'Neue oder geänderte Rückmeldungen dürfen höchstens 2048 Zeichen enthalten.',
        ]);
        if (array_key_exists('status', $validated)) {
            $validated['status'] ??= [];
            $validated['status']['evaluation_pdfs'] = $course_work->status['evaluation_pdfs'] ?? [];
            $validated['status']['dispatch_logs'] = $course_work->status['dispatch_logs'] ?? [];
            $validated['status']['dispatch_notifications'] = $course_work->status['dispatch_notifications'] ?? [];
            $validated['status']['dispatch_attempts'] = $course_work->status['dispatch_attempts'] ?? [];
            $validated['status']['folder_import_sources'] = $course_work->status['folder_import_sources'] ?? [];
            foreach (['creation_receipt', 'student_distribution', 'student_task_files', 'assessment_json_imports', 'assessment_json_packages', 'submission_checks', 'provision_events', 'provision_imports', 'communicated_deadline_at', 'communicated_deadline_date', 'final_deadline_saved', 'final_deadline_sync_pending', 'completion_override', 'multiple_choice_tests', 'deletion_receipt', ...self::ManualCompletionFields, ...TeachingCourseWork::ManagedWorkflowFields] as $field) {
                if (isset($course_work->status[$field])) {
                    $validated['status'][$field] = $course_work->status[$field];
                } else {
                    unset($validated['status'][$field]);
                }
            }
            if (isset($course_work->status['folder_imported_at'])) {
                $validated['status']['folder_imported_at'] = $course_work->status['folder_imported_at'];
            } else {
                unset($validated['status']['folder_imported_at']);
            }
        }
        if (array_key_exists('completion_override', $validated)) {
            $validated['status'] ??= $course_work->status ?? [];
            $validated['status']['completion_override'] = (bool) $validated['completion_override'];
        }
        unset($validated['completion_override']);
        foreach (self::ManualCompletionFields as $field) {
            if (array_key_exists($field, $validated)) {
                $validated['status'] ??= $course_work->status ?? [];
                $validated['status'][$field] = (bool) $validated[$field];
            }
            unset($validated[$field]);
        }
        $validated['maximum_plus'] = $maximumPlus;

        if (! array_key_exists('groups', $validated)) {
            Validator::make(['groups' => $course_work->groups], [
                'groups.*.grade' => $gradeRules,
                'groups.*.grades.*.grade' => $gradeRules,
            ])->validate();
            $validated['groups'] = $course_work->groups;
        }

        if (array_key_exists('automation_enabled', $validated) || array_key_exists('status', $validated)) {
            $validated['status'] ??= $course_work->status ?? [];
            $validated['status']['automation_enabled'] = array_key_exists('automation_enabled', $validated)
                ? (bool) $validated['automation_enabled'] : $course_work->automationEnabled();
        }
        unset($validated['automation_enabled']);

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id, $course_work->is_group_work);
        $expectedDeadline = $validated['expected_final_deadline'] ?? null;
        unset($validated['expected_final_deadline']);
        if ($titleOnly) {
            $validated = ['title' => $validated['title']];
        }

        if (array_key_exists('title', $validated)) {
            try {
                $course_work = DB::transaction(function () use ($course_work, $validated, $auth_user, $entrySyncService, $titleOnly, $expectedDeadline): TeachingCourseWork {
                    $locked = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
                    abort_if(isset($locked->status['deletion_receipt']), 409,
                        'Die Löschung ist noch nicht abgeschlossen. Bitte die Zuordnung prüfen und erneut löschen.');
                    abort_unless($locked->title === $course_work->getOriginal('title'), 409,
                        'Der Titel wurde inzwischen geändert. Bitte die Arbeit erneut öffnen.');
                    if ($validated['title'] !== $locked->title) {
                        try {
                            $renamed = app(DropboxModuleLinks::class)->renamePreparedExamFolders($locked, $auth_user, $validated['title'], $validated);
                            if ($renamed !== []) {
                                $folder = TeachingWorkDropboxFolder::query()->where('teaching_course_work_id', $locked->id)->lockForUpdate()->first();
                                if ($folder && isset($renamed[$folder->folder_id])) {
                                    $folder->update(['folder_name' => $renamed[$folder->folder_id]]);
                                }
                            }
                        } catch (HttpExceptionInterface $exception) {
                            throw $exception;
                        } catch (Throwable $exception) {
                            report($exception);
                            app(DropboxModuleLinks::class)->forgetExerciseCache($locked, $auth_user);
                            app(TeachingWorkCollectionStatus::class)->forget($locked);
                            abort(409, 'Der Titel konnte nicht vollständig gespeichert werden. Ein Prüfungsordner wurde möglicherweise bereits umbenannt. Bitte denselben Titel erneut speichern und den Stand prüfen.');
                        }
                    }
                    app(TeachingWorkDeadlineSync::class)->saveWorkWithDeadline($locked, $validated, expected: $expectedDeadline);
                    if (! $titleOnly) {
                        $entrySyncService->syncWork($locked->fresh());
                    }

                    return $locked;
                });
            } catch (HttpExceptionInterface|ValidationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report($exception);
                app(DropboxModuleLinks::class)->forgetExerciseCache($course_work, $auth_user);
                app(TeachingWorkCollectionStatus::class)->forget($course_work);
                abort(409, 'Der Titel konnte nicht vollständig gespeichert werden. Ein Prüfungsordner wurde möglicherweise bereits umbenannt. Bitte denselben Titel erneut speichern und den Stand prüfen.');
            }
            app(DropboxModuleLinks::class)->forgetExerciseCache($course_work, $auth_user);
        } else {
            $course_work = DB::transaction(function () use ($course_work, $validated, $entrySyncService, $expectedDeadline): TeachingCourseWork {
                $locked = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                abort_if(isset($locked->status['deletion_receipt']), 409,
                    'Die Löschung ist noch nicht abgeschlossen. Bitte die Zuordnung prüfen und erneut löschen.');
                app(TeachingWorkDeadlineSync::class)->saveWorkWithDeadline($locked, $validated, expected: $expectedDeadline);
                $entrySyncService->syncWork($locked->fresh());

                return $locked;
            });
        }

        $response = [];
        if (($course_work->status['final_deadline_sync_pending'] ?? false) && $request->hasAny(['finish_until_date', 'finish_until_time'])) {
            try {
                app(TeachingWorkDeadlineSync::class)->syncLinkedFolders($course_work, $auth_user);
            } catch (Throwable $exception) {
                report($exception);
                $response['deadline_sync_warning'] = 'Schooltool hat die Abgabefrist gespeichert. Die Dropbox-Fristdateien konnten nicht vollständig aktualisiert werden.';
            }
        }

        return response()->json(['data' => $entrySyncService->serializeWork($course_work->fresh('teachingCourseWorkGroupStudents'))] + $response);
    }

    public function destroy(Request $request, TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        if ($request->boolean('stream_progress') && ! $request->attributes->get('work_deletion_stream')) {
            $request->attributes->set('work_deletion_stream', true);

            return app(TeachingWorkRefreshProgress::class)->stream($request, function () use ($request, $course_work, $entrySyncService): JsonResponse {
                $response = $this->destroy($request, $course_work, $entrySyncService);
                if ($response->getStatusCode() === 204) {
                    return response()->json(['deleted' => true]);
                }
                if ($response->getStatusCode() === 200) {
                    return $response;
                }
                throw new HttpResponseException($response);
            });
        }

        $actor = $this->teachingCourseActor($auth_user, $course);
        $progressService = app(TeachingWorkRefreshProgress::class);
        $progress = ['state' => 'running', 'active' => 'delete_check', 'completed' => [], 'skipped' => [],
            'steps' => ['delete_check', 'delete_folders', 'delete_mc_files', 'delete_records']];
        $progressService->save(null, $progress);
        $filesMayHaveChanged = false;
        $skippedReason = null;
        $deleteWorkOnly = function (TeachingCourseWork $work, TeachingWorkDeletionConflict $exception) use ($entrySyncService, $progressService, &$progress, &$skippedReason): void {
            $skippedReason = $exception->getMessage();
            $progress['completed'] = ['delete_check'];
            $progress['skipped'] = ['delete_folders', 'delete_mc_files'];
            $progress['active'] = 'delete_records';
            $progressService->save(null, $progress);
            $entrySyncService->deleteForWork($work);
            $work->delete();
        };
        try {
            DB::transaction(function () use ($course_work, $actor, $deleteWorkOnly): void {
                $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                try {
                    app(TeachingWorkDeletion::class)->prepare($work, $actor, createReceipt: true);
                } catch (TeachingWorkDeletionConflict $exception) {
                    $deleteWorkOnly($work, $exception);
                }
            });
            if ($skippedReason === null) {
                DB::transaction(function () use ($course_work, $entrySyncService, $actor, $progressService, $deleteWorkOnly, &$progress, &$filesMayHaveChanged): void {
                    $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                    try {
                        $plan = app(TeachingWorkDeletion::class)->prepare($work, $actor, createReceipt: false);
                    } catch (TeachingWorkDeletionConflict $exception) {
                        $deleteWorkOnly($work, $exception);

                        return;
                    }
                    $tests = $plan['tests'];
                    $testFolders = app(McTestFolder::class);
                    $modules = app(DropboxModuleLinks::class);
                    $targets = $plan['targets'];
                    $progress['completed'][] = 'delete_check';
                    $progress['active'] = 'delete_folders';
                    $progressService->save(null, $progress);
                    if ($targets && $targets['folders'] !== []) {
                        $modules->forgetExerciseCache($work, $actor);
                        app(TeachingWorkCollectionStatus::class)->forget($work);
                        foreach ($targets['folders'] as $folder) {
                            $filesMayHaveChanged = true;
                            app(DropboxWorkImport::class)->deleteExamFolder($targets['connection'], $folder['id'], $folder['path']);
                        }
                    }
                    $progress[$targets && $targets['folders'] !== [] ? 'completed' : 'skipped'][] = 'delete_folders';
                    $progress['active'] = 'delete_mc_files';
                    $progressService->save(null, $progress);
                    foreach ($plan['history'] as $file) {
                        $filesMayHaveChanged = true;
                        app(McTestExchange::class)->deleteHistoryFile($file);
                    }
                    foreach ($plan['bindings'] as $binding) {
                        $filesMayHaveChanged = true;
                        $testFolders->deleteBinding($binding);
                    }
                    $progress[$plan['history'] !== [] || $plan['bindings'] !== [] ? 'completed' : 'skipped'][] = 'delete_mc_files';
                    $progress['active'] = 'delete_records';
                    $progressService->save(null, $progress);
                    foreach ($tests as $test) {
                        $test->attempts()->delete();
                        $test->delete();
                    }
                    $entrySyncService->deleteForWork($work);
                    $work->delete();
                });
            }
            $progress['completed'][] = 'delete_records';
            $progress['active'] = null;
            $progress['state'] = 'done';
            $progressService->save(null, $progress);
        } catch (Throwable $exception) {
            $progress['state'] = 'failed';
            $progressService->save(null, $progress);
            if ($filesMayHaveChanged) {
                report($exception);
                abort(409, 'Das Löschen konnte nicht vollständig bestätigt werden. Einzelne Prüfungsordner oder MC-Dateien können bereits entfernt sein. Bitte den Stand von Arbeit und MC-Test prüfen und gegebenenfalls erneut löschen.');
            }
            throw $exception;
        }

        if ($skippedReason !== null) {
            return response()->json(['deleted' => true, 'skipped_unverified' => true,
                'message' => 'Arbeit gelöscht. Prüfungsordner und MC-Tests wurden bei diesem Vorgang erhalten, weil Zuordnungen unklar, geteilt oder nicht überprüfbar waren. '.$skippedReason])
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json(null, 204);
    }

    /**
     * Return the shared validation rules for store/update of a course work.
     */
    private function workValidationRules(array $typeRules, bool $isStore, TeachingCourse $course, array $gradeRules): array
    {
        $maximumGroupSize = $course->teachingCourseStudents()
            ->whereNull('canceled_at')
            ->with(['user', 'import116.user'])
            ->get()
            ->filter(fn ($membership): bool => app(TeachingCourseStudentActivity::class)->active($membership, $course))
            ->count();

        $rules = [
            'type' => $typeRules,
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1024',
            'is_group_work' => 'sometimes|boolean',
            'automation_enabled' => 'sometimes|boolean',
            'group_size' => "nullable|integer|min:2|max:{$maximumGroupSize}",
            'is_random_groups' => 'sometimes|boolean',
            'date_for_all_groups' => 'nullable|date',
            'finish_until_date' => 'nullable|date',
            'finish_until_time' => 'nullable|date_format:H:i',
            'groups' => 'nullable|array',
            'groups.*.student_ids' => 'nullable|array',
            'groups.*.student_ids.*' => 'integer',
            'groups.*.date' => 'nullable|date',
            'groups.*.comment' => 'nullable|string|max:2048',
            'groups.*.grade' => $gradeRules,
            'groups.*.grades' => 'nullable|array',
            'groups.*.grades.*.student_id' => 'required|integer',
            'groups.*.grades.*.grade' => $gradeRules,
            'groups.*.points' => 'nullable|array',
            'groups.*.points.*.student_id' => 'required|integer',
            'groups.*.points.*.points' => 'nullable|numeric',
            'groups.*.comments' => 'nullable|array',
            'groups.*.comments.*.student_id' => 'required|integer',
            'groups.*.comments.*.comment' => 'nullable|string|max:2048',
            'groups.*.name' => 'nullable|string|max:255',
            'status' => 'nullable|array',
        ];

        if ($isStore) {
            $rules['teaching_course_id'] = 'required|integer|exists:teaching_courses,id';
        } else {
            $rules['completion_override'] = 'sometimes|boolean';
            $rules['expected_final_deadline'] = 'sometimes|required|array:date,time';
            $rules['expected_final_deadline.date'] = 'present_with:expected_final_deadline|nullable|date_format:Y-m-d';
            $rules['expected_final_deadline.time'] = 'present_with:expected_final_deadline|nullable|date_format:H:i';
            foreach (self::ManualCompletionFields as $field) {
                $rules[$field] = 'sometimes|boolean';
            }
        }

        return $rules;
    }

    /** @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    private function preserveStoredFeedbackRules(Request $request, TeachingCourseWork $work, array $rules): array
    {
        $incomingGroups = $request->input('groups', []);
        if (! is_array($incomingGroups)) {
            return $rules;
        }

        foreach ($incomingGroups as $groupIndex => $group) {
            if (! is_array($group)) {
                continue;
            }

            foreach ($work->groups ?? [] as $storedGroup) {
                $studentIds = $group['student_ids'] ?? [];
                $storedStudentIds = $storedGroup['student_ids'] ?? [];
                if (is_array($studentIds) && is_array($storedStudentIds)) {
                    $studentIds = array_map('strval', $studentIds);
                    $storedStudentIds = array_map('strval', $storedStudentIds);
                    sort($studentIds);
                    sort($storedStudentIds);
                    if ($studentIds !== [] && $studentIds === $storedStudentIds
                        && is_string($group['comment'] ?? null)
                        && $group['comment'] === ($storedGroup['comment'] ?? null)) {
                        $rules["groups.{$groupIndex}.comment"] = 'nullable|string';
                    }
                }

                foreach (is_array($group['comments'] ?? null) ? $group['comments'] : [] as $commentIndex => $comment) {
                    if (! is_array($comment) || ! is_string($comment['comment'] ?? null) || ! isset($comment['student_id'])) {
                        continue;
                    }
                    foreach ($storedGroup['comments'] ?? [] as $storedComment) {
                        if ((string) ($storedComment['student_id'] ?? '') === (string) $comment['student_id']
                            && $comment['comment'] === ($storedComment['comment'] ?? null)) {
                            $rules["groups.{$groupIndex}.comments.{$commentIndex}.comment"] = 'nullable|string';
                        }
                    }
                }
            }
        }

        return $rules;
    }

    private function validatedMaximumPlus(Request $request, TeachingCourse $course, User $actor, TeachingCourseStudentEntryService $entryService, ?TeachingCourseWork $work = null): ?int
    {
        $definition = $entryService->entryDefinitionsForCourse($actor, $course)
            ->firstWhere('short_name', $request->input('type', $work?->type));
        if ($definition?->category !== 'Benotung' || ! $definition->has_properties
            || $definition->properties_mode !== 'plus' || ! $definition->allows_maximum_plus) {
            return null;
        }

        $validated = Validator::make([
            'maximum_plus' => $request->input('maximum_plus', $work?->maximum_plus),
        ], [
            'maximum_plus' => ['required', 'integer:strict', 'min:1', 'max:4294967295'],
        ], [
            'maximum_plus.required' => 'Bitte die maximal erreichbare Anzahl an Plus angeben.',
            'maximum_plus.integer' => 'Die maximale Anzahl an Plus muss eine ganze Zahl sein.',
            'maximum_plus.min' => 'Die maximale Anzahl an Plus muss mindestens 1 sein.',
        ])->validate();

        return $validated['maximum_plus'];
    }

    private function appendMaximumPlusGradeRule(array &$gradeRules, ?int $maximumPlus): void
    {
        if ($maximumPlus === null) {
            return;
        }

        $gradeRules[] = function (string $attribute, mixed $value, Closure $fail) use ($maximumPlus): void {
            if (is_string($value) && preg_match('/^\++$/', trim($value)) === 1 && strlen(trim($value)) > $maximumPlus) {
                $fail('Die Anzahl an Plus darf die maximal erreichbare Anzahl nicht überschreiten.');
            }
        };
    }
}
