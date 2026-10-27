<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FinalizeMultipleChoiceTest;
use App\Mail\MultipleChoiceInvitation;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\DropboxWorkImport;
use App\Services\FeaturePreviewService;
use App\Services\MultipleChoice\McAttemptActivityService;
use App\Services\MultipleChoice\McCatalogTemplateSync;
use App\Services\MultipleChoice\McImportService;
use App\Services\MultipleChoice\McModuleTemplateSource;
use App\Services\MultipleChoice\McQuestionSummary;
use App\Services\MultipleChoice\McQuestionValidator;
use App\Services\MultipleChoice\McTestExchange;
use App\Services\MultipleChoice\McTestFolder;
use App\Services\MultipleChoice\McTestFolderImport;
use App\Services\MultipleChoice\McTestService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MultipleChoiceController extends Controller
{
    public function folderStatus(McTest $test, McTestFolder $folders): JsonResponse
    {
        $this->authorizeOwned($test);

        return response()->json($folders->status($test, $this->teacher(), true))->header('Cache-Control', 'private, no-store');
    }

    public function folderStore(Request $request, McTest $test, McTestFolder $folders): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['provider' => ['required', 'in:dropbox,local'],
            'folder_id' => ['required_if:provider,dropbox', 'nullable', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/'],
            'name' => ['required_if:provider,local', 'nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1f]/']]);

        return response()->json($folders->bind($test, $this->teacher(), $data));
    }

    public function folderConnect(Request $request, McTest $test, DropboxWorkImport $dropbox): JsonResponse
    {
        $this->authorizeOwned($test);
        $state = bin2hex(random_bytes(32));
        $request->session()->put('teaching_dropbox_oauth', ['state' => $state, 'user_id' => $this->teacher()->id,
            'mc_test_id' => $test->id, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return response()->json(['url' => $dropbox->authorizationUrl($state, true)]);
    }

    public function completeFolderConnection(Request $request, DropboxWorkImport $dropbox, array $pending): RedirectResponse
    {
        $test = McTest::findOrFail($pending['mc_test_id']);
        $this->authorizeOwned($test);
        $status = 'cancelled';
        if (! $request->query('error')) {
            $data = $request->validate(['code' => ['required', 'string', 'max:2048']]);
            $dropbox->connect($this->teacher(), $data['code'], true);
            $status = 'connected';
        }

        return redirect('/admin/teaching-tools/multiple-choice?'.http_build_query([
            'catalog' => $test->catalog_id, 'view' => 'test', 'test' => $test->id, 'folder' => '1', 'dropbox' => $status]));
    }

    public function folderList(Request $request, McTest $test, DropboxWorkImport $dropbox, McTestFolder $folders): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['folder_id' => ['nullable', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/'],
            'cursor' => ['nullable', 'string', 'max:4096'], 'suggest' => ['sometimes', 'boolean']]);
        if ($request->boolean('suggest')) {
            return response()->json($folders->suggestions($test, $this->teacher()));
        }

        return response()->json($dropbox->folders($dropbox->connection($this->teacher()), $data['folder_id'] ?? '', $data['cursor'] ?? null));
    }

    public function folderExport(Request $request, McTest $test, McTestFolder $folders): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['previous' => ['nullable', 'array'], 'dropbox' => ['sometimes', 'boolean']]);
        $links = $test->status === 'published' && $test->access_links_disabled_at === null
            ? $this->exportExercise($request, $test)->getData(true) : [];

        return response()->json($folders->export($test, $this->teacher(), $links, $data['previous'] ?? null, $request->boolean('dropbox')));
    }

    public function folderImport(Request $request, McTest $test, McTestFolder $folders, McTestExchange $exchange): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['package' => ['nullable', 'array'], 'apply' => ['sometimes', 'boolean'],
            'hash' => ['required_if:apply,1', 'nullable', 'string', 'size:64']]);
        $package = $data['package'] ?? $folders->read($test, $this->teacher());
        if (strlen(json_encode($package, JSON_THROW_ON_ERROR)) > 6 * 1024 * 1024) {
            throw ValidationException::withMessages(['package' => 'Die Testdatei ist größer als 6 MiB.']);
        }
        if (! $request->boolean('apply')) {
            return response()->json($exchange->preview($test, $package));
        }
        $result = DB::transaction(function () use ($exchange, $test, $package, $data, $folders): array {
            $result = $exchange->apply($test, $this->teacher(), $package, $data['hash']);
            $folders->acknowledge($test, $this->teacher(), $package);

            return $result;
        });

        return response()->json($result);
    }

    public function folderAcknowledge(Request $request, McTest $test, McTestFolder $folders): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['package' => ['required', 'array']]);
        $folders->acknowledge($test, $this->teacher(), $data['package']);

        return response()->json(['remembered' => true]);
    }

    public function folderCheckWrite(McTest $test, McTestFolder $folders): JsonResponse
    {
        $this->authorizeOwned($test);

        return response()->json($folders->verifyWrite($test, $this->teacher()));
    }

    public function __construct(
        private McImportService $imports,
        private McQuestionValidator $questions,
        private McTestService $tests,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->teacher();
        if (! Schema::hasTable('mc_catalogs') || ! Schema::hasTable('mc_tests') || ! Schema::hasTable('mc_attempts')) {
            return response()->json([
                'available' => false,
                'message' => 'Multiple Choice benötigt noch die Freischaltung der neuen Datenbanktabellen durch die Administration.',
                'catalogs' => [],
                'tests' => [],
            ]);
        }

        return response()->json([
            'available' => true,
            'catalogs' => McCatalog::query()->where('school_id', $user->school_id)->where('owner_id', $user->id)->with('tests:id,catalog_id')->orderBy('title')->get()->map(fn (McCatalog $catalog): array => [...app(McCatalogTemplateSync::class)->payload($catalog), 'test_ids' => $catalog->tests->modelKeys()]),
            'tests' => McTest::query()->where('school_id', $user->school_id)->where('owner_id', $user->id)->whereNotNull('schoolyear_id')->where('schoolyear_id', $user->schoolyear_id)->withCount('attempts')->latest('id')->get(),
            'unassigned_tests' => McTest::query()->where('school_id', $user->school_id)->where('owner_id', $user->id)->whereNull('schoolyear_id')->withCount('attempts')->latest('id')->get(),
            'schoolyear' => Schoolyear::query()->where('school_id', $user->school_id)->find($user->schoolyear_id, ['id', 'name', 'from', 'until']),
            'server_now' => now()->toIso8601String(),
        ]);
    }

    public function createCatalog(Request $request): JsonResponse
    {
        $user = $this->teacher();

        return $this->saveCatalog($request, new McCatalog(['school_id' => $user->school_id, 'owner_id' => $user->id]));
    }

    public function updateCatalog(Request $request, McCatalog $catalog): JsonResponse
    {
        $this->authorizeOwned($catalog);

        return $this->saveCatalog($request, $catalog);
    }

    public function previewImport(Request $request): JsonResponse
    {
        $actor = $this->teacher();
        if ($request->has('handshake')) {
            $data = $request->validate(['filename' => ['required', 'string', 'regex:/\Ahandshake\.json\z/i'],
                'handshake' => ['required', 'string', 'max:4096'], 'catalog_id' => ['nullable', 'integer', 'min:1'],
                'source_id' => ['nullable', 'string', 'regex:/\Aid:[A-Za-z0-9_-]+\z/']], [
                    'filename.regex' => 'Zulässig ist ausschließlich die Datei handshake.json.',
                    'handshake.required' => 'Bitte eine gültige handshake.json auswählen.',
                ]);
            if (isset($data['catalog_id'])) {
                $this->authorizeOwned(McCatalog::query()->findOrFail($data['catalog_id']));
            }

            return response()->json(app(McModuleTemplateSource::class)->preview($actor, $data['catalog_id'] ?? null,
                $data['handshake'], $data['source_id'] ?? null))->header('Cache-Control', 'private, no-store');
        }
        $data = $request->validate([
            'text' => ['required', 'string', 'max:1048576'],
            'format' => ['sometimes', Rule::in(['markdown', 'json'])],
            'generate' => ['sometimes', 'boolean'],
        ]);
        $preview = ($data['generate'] ?? false)
            ? $this->imports->generateDraft($data['text'])
            : $this->imports->preview($data['text'], $data['format'] ?? 'markdown');

        return response()->json($preview);
    }

    public function templateSyncPreview(Request $request, McCatalog $catalog, McCatalogTemplateSync $sync): JsonResponse
    {
        $this->authorizeOwned($catalog);
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:255'], 'questions' => ['sometimes', 'array', 'max:2000'],
            'expected_version' => ['required_with:title,questions', 'string', 'size:64']]);

        return response()->json($sync->serialize($catalog, function () use ($sync, $catalog, $data): array {
            $catalog->refresh();

            return $sync->preview($this->teacher(), $catalog, $data);
        }))->header('Cache-Control', 'private, no-store');
    }

    public function templateSyncApply(Request $request, McCatalog $catalog, McCatalogTemplateSync $sync): JsonResponse
    {
        $this->authorizeOwned($catalog);
        $data = $request->validate(['token' => ['required', 'string', 'max:8192']]);

        return response()->json($sync->apply($this->teacher(), $catalog, $data['token']))->header('Cache-Control', 'private, no-store');
    }

    public function disconnectTemplate(Request $request, McCatalog $catalog, McCatalogTemplateSync $sync): JsonResponse
    {
        $this->authorizeOwned($catalog);
        $data = $request->validate(['recovery_token' => ['sometimes', 'string', 'max:8192']]);

        return response()->json($sync->disconnect($this->teacher(), $catalog, $data['recovery_token'] ?? null))->header('Cache-Control', 'private, no-store');
    }

    public function questionSummaryPreview(McCatalog $catalog, McQuestionSummary $summaries): JsonResponse
    {
        $this->authorizeOwned($catalog);

        return response()->json($summaries->preview($this->teacher(), $catalog))->header('Cache-Control', 'private, no-store');
    }

    public function questionSummaryApply(Request $request, McCatalog $catalog, McQuestionSummary $summaries): JsonResponse
    {
        $this->authorizeOwned($catalog);
        $data = $request->validate(['token' => ['required', 'string', 'max:8192']]);

        return response()->json($summaries->apply($this->teacher(), $catalog, $data['token']))->header('Cache-Control', 'private, no-store');
    }

    public function rosterPreview(Request $request): JsonResponse
    {
        $user = $this->teacher();
        if ($request->has('work_export')) {
            $data = $request->validate([
                'work_export' => ['required', 'array'],
                'work_export.schema_version' => ['required', 'integer', 'in:1'],
                'work_export.kind' => ['required', 'in:schooltool.ai-work-export'],
                'work_export.school.id' => ['required', 'integer', Rule::in([$user->school_id])],
                'work_export.schoolyear.id' => ['required', 'integer', Rule::in([$user->schoolyear_id])],
                'work_export.work.id' => ['required', 'integer', 'min:1'],
                'work_export.work.title' => ['required', 'string', 'max:255'],
                'work_export.work.scheduled_date' => ['nullable', 'date_format:Y-m-d'],
                'work_export.course.id' => ['required', 'integer', 'min:1'],
                'work_export.course.title' => ['required', 'string', 'max:255'],
                'work_export.course_roster.complete' => ['required', 'accepted'],
                'work_export.course_roster.participants' => ['required', 'array', 'min:1', 'max:1000'],
                'work_export.course_roster.participants.*.schooltool_person_id' => ['nullable', 'integer', 'min:1'],
                'work_export.course_roster.participants.*.first_name' => ['required', 'string', 'max:255'],
                'work_export.course_roster.participants.*.last_name' => ['required', 'string', 'max:255'],
                'work_export.course_roster.participants.*.class_name' => ['nullable', 'string', 'max:255'],
                'work_export.course_roster.participants.*.active' => ['required', 'boolean'],
            ]);

            return response()->json($this->imports->workRosterPreview($data['work_export'], (int) $user->school_id));
        }
        $data = $request->validate(['csv' => ['required', 'string', 'max:1048576']]);

        return response()->json($this->imports->rosterPreview($data['csv'], (int) $user->school_id));
    }

    public function testFolderFolders(Request $request, McTestFolderImport $folders): JsonResponse
    {
        $user = $this->teacher();
        $data = $request->validate(['folder_id' => ['nullable', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/'],
            'cursor' => ['nullable', 'string', 'max:16384']]);

        return response()->json($folders->folders($user, $data['folder_id'] ?? '', $data['cursor'] ?? null))
            ->header('Cache-Control', 'private, no-store');
    }

    public function testFolderPreview(Request $request, McTestFolderImport $folders): JsonResponse
    {
        $user = $this->teacher();
        $data = $request->validate(['folder_id' => ['required', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/']]);

        return response()->json($folders->preview($user, $data['folder_id']))->header('Cache-Control', 'private, no-store');
    }

    public function testFolderResolve(Request $request, McTestFolderImport $folders): JsonResponse
    {
        $user = $this->teacher();
        $data = $request->validate(['filename' => ['required', 'string', 'max:255'],
            'handshake' => ['nullable', 'string', 'max:4096'], 'content_hash' => ['nullable', 'string', 'regex:/\A[0-9a-f]{64}\z/']]);

        if (preg_match('/\A[^\/\\\\\x00-\x1f]+\.md\z/i', $data['filename']) === 1 && isset($data['content_hash'])) {
            return response()->json($folders->resolveMarkdown($user, $data['filename'], $data['content_hash']))->header('Cache-Control', 'private, no-store');
        }
        abort_unless($data['filename'] === 'handshake.json' && isset($data['handshake']), 422, 'Bitte eine handshake.json oder eine Markdown-Datei aus dem Testordner auswählen.');

        return response()->json($folders->resolve($user, $data['handshake']))->header('Cache-Control', 'private, no-store');
    }

    public function createTest(Request $request): JsonResponse
    {
        $user = $this->teacher();
        $data = $request->validate([
            'catalog_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'question_count' => ['required', 'integer', 'in:10'],
            'total_points' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'exam_mode' => ['sometimes', 'boolean'],
            'start_mode' => ['sometimes', 'in:common,individual'],
            'closes_at' => ['required_if:start_mode,individual', 'nullable', 'date', 'after:now'],
            'duration_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'lead_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'balance_types' => ['required', 'boolean'],
            'shuffle_questions' => ['required', 'boolean'],
            'shuffle_options' => ['required', 'boolean'],
            'roster_csv' => ['required', 'string', 'max:1048576'],
            'test_folder_proof' => ['sometimes', 'nullable', 'string', 'max:8192'],
        ]);
        $catalog = McCatalog::query()->findOrFail($data['catalog_id']);
        $this->authorizeOwned($catalog);
        $roster = $this->imports->rosterPreview($data['roster_csv'], (int) $user->school_id);
        if (! ($roster['valid'] ?? false) || empty($roster['rows'])) {
            throw ValidationException::withMessages(['roster_csv' => ['Die gesamte Schülerliste muss eindeutig und fehlerfrei zugeordnet sein. Bitte prüfen Sie die Vorschau.']]);
        }

        $schoolyear = Schoolyear::query()->where('school_id', $user->school_id)->find($user->schoolyear_id);
        if ($schoolyear === null) {
            throw ValidationException::withMessages(['schoolyear' => 'Bitte wählen Sie zuerst ein gültiges Schuljahr dieser Schule.']);
        }

        $studentIds = [];
        foreach ($roster['rows'] as $row) {
            if (($row['status'] ?? null) !== 'matched' || empty($row['user_id']) || ! empty($row['errors'])) {
                throw ValidationException::withMessages(['roster_csv' => ['Mindestens eine Zeile der Schülerliste ist nicht eindeutig zugeordnet.']]);
            }
            $studentIds[] = (int) $row['user_id'];
        }

        $folderImporter = app(McTestFolderImport::class);
        $folderProof = isset($data['test_folder_proof'])
            ? $folderImporter->verify($user, $data['test_folder_proof'], $data['roster_csv']) : null;
        $folderBinding = null;
        $test = DB::transaction(function () use ($data, $catalog, $user, $studentIds, $schoolyear, $folderImporter, $folderProof, &$folderBinding): McTest {
            $test = McTest::query()->create([
                'school_id' => $user->school_id,
                'schoolyear_id' => $schoolyear->id,
                'owner_id' => $user->id,
                'catalog_id' => $catalog->id,
                'title' => trim($data['title']),
                'status' => 'draft',
                'question_count' => $data['question_count'],
                'total_points' => $data['total_points'],
                'exam_mode' => $data['exam_mode'] ?? false,
                'start_mode' => $data['start_mode'] ?? 'common',
                'closes_at' => ($data['start_mode'] ?? 'common') === 'individual'
                    ? CarbonImmutable::parse($data['closes_at'])->setTimezone(config('app.timezone')) : null,
                'duration_seconds' => $data['duration_seconds'],
                'lead_seconds' => $data['lead_seconds'],
                'balance_types' => $data['balance_types'],
                'shuffle_questions' => $data['shuffle_questions'],
                'shuffle_options' => $data['shuffle_options'],
            ]);

            $test = $this->tests->prepare($test, $studentIds);
            if ($folderProof !== null) {
                $folderBinding = $folderImporter->bind($test, $user, $folderProof);
            }

            return $test;
        });

        return response()->json(['test' => $test, 'attempts' => $this->attemptPayloads($test), 'folder_binding' => $folderBinding], 201);
    }

    public function publishTest(McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        abort_unless($test->snapshot !== null && $test->attempts()->exists(), 422, 'Bitte zuerst den Test mit Teilnehmern erstellen.');
        $test = $this->tests->publish($test, []);
        if ($test->start_mode === 'individual') {
            FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($test->closes_at)->afterCommit();
        }

        return response()->json(['test' => $test, 'attempts' => $this->attemptPayloads($test)]);
    }

    public function deleteCatalog(Request $request, McCatalog $catalog): JsonResponse
    {
        $this->authorizeOwned($catalog);
        $data = $request->validate(['test_ids' => ['present', 'array'], 'test_ids.*' => ['required', 'integer', 'distinct']]);
        $sync = app(McCatalogTemplateSync::class);
        $sync->serialize($catalog, function () use ($catalog, $data, $sync): void {
            $catalog->refresh();
            $sync->assertEditable($catalog, $sync->version($catalog));
            DB::transaction(function () use ($catalog, $data): void {
                $locked = McCatalog::query()->lockForUpdate()->findOrFail($catalog->id);
                $tests = $locked->tests()->orderBy('id')->lockForUpdate()->get();
                $expected = array_map('intval', $data['test_ids']);
                sort($expected);
                if ($tests->modelKeys() !== $expected) {
                    throw ValidationException::withMessages(['catalog' => ['Die Testliste hat sich geändert. Bitte schließen Sie den Dialog, aktualisieren Sie die Übersicht und prüfen Sie die Löschung erneut.']]);
                }
                foreach ($tests as $test) {
                    $this->authorizeOwned($test);
                    $test->attempts()->delete();
                    $test->delete();
                }
                $locked->delete();
            }, 3);
        });

        return response()->json(['deleted' => true]);
    }

    public function deleteTest(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        DB::transaction(function () use ($test): void {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $locked->attempts()->delete();
            $locked->delete();
        }, 3);

        return response()->json(['deleted' => true]);
    }

    public function renameTest(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $updated = DB::transaction(function () use ($test, $data): McTest {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            McTest::query()->whereKey($locked->id)->update(['title' => trim($data['title'])]);

            return $locked->fresh();
        });

        return response()->json(['test' => $updated]);
    }

    public function updateTestSettings(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'question_count' => ['required', 'integer', 'min:1', 'max:2000'],
            'total_points' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'exam_mode' => ['required', 'boolean'],
            'start_mode' => ['required', 'in:common,individual'],
            'closes_at' => ['required_if:start_mode,individual', 'nullable', 'date', 'after:now'],
            'duration_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'lead_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'balance_types' => ['required', 'boolean'],
            'shuffle_questions' => ['required', 'boolean'],
            'shuffle_options' => ['required', 'boolean'],
        ]);
        $test = $this->tests->updateSettings($test, $data, (int) $this->teacher()->id);
        if ($test->start_mode === 'individual') {
            FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($test->closes_at)->afterCommit();
        }

        return response()->json(['test' => $test, 'attempts' => $this->attemptPayloads($test)]);
    }

    public function setAccessLinks(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($test, $data): void {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            McTest::query()->whereKey($locked->id)->update(['access_links_disabled_at' => $data['enabled'] ? null : ($locked->access_links_disabled_at ?? now())]);
        });

        return response()->json(['test' => $test->fresh()]);
    }

    public function setExamMode(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($test, $data): void {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            if ($locked->status !== 'published') {
                throw ValidationException::withMessages(['test' => 'Der Test ist noch nicht freigegeben.']);
            }
            if (! $data['enabled']) {
                foreach ($locked->attempts()->orderBy('id')->lockForUpdate()->get() as $attempt) {
                    app(McAttemptActivityService::class)->finish($attempt, CarbonImmutable::now()->min($attempt->effectiveDeadline($locked) ?? CarbonImmutable::now()));
                }
            }
            McTest::query()->whereKey($locked->id)->update(['exam_mode' => $data['enabled']]);
        });

        return response()->json(['test' => $test->fresh()]);
    }

    public function pauseTest(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $test = $this->tests->pause($test, (int) $this->teacher()->id);

        return response()->json(['test' => $test]);
    }

    public function resumeTest(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $test = $this->tests->resume($test, (int) $this->teacher()->id);
        foreach ($test->attempts()->whereNull('completed_at')->get() as $attempt) {
            if ($attempt->effectiveDeadline($test) !== null) {
                FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($attempt->effectiveDeadline($test))->afterCommit();
            }
        }

        return response()->json(['test' => $test]);
    }

    public function closeTest(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $test = $this->tests->closeEarly($test);

        return response()->json(['test' => $test, 'attempts' => $this->attemptPayloads($test)]);
    }

    public function changeRemainingTime(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['remaining_seconds' => ['required', 'integer', 'min:1', 'max:86400']]);
        $test = $this->tests->changeRemainingTime($test, $data['remaining_seconds']);
        FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($test->ends_at)->afterCommit();

        return response()->json(['test' => $test, 'server_now' => now()->toIso8601String()]);
    }

    public function reopenAttempts(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        abort_unless(Schema::hasColumns('mc_attempts', ['reopened_until', 'reopen_history']), 503, 'Das individuelle Wiederöffnen ist noch nicht eingerichtet.');
        $data = $request->validate([
            'attempt_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'attempt_ids.*' => ['required', 'integer', 'distinct'],
            'remaining_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);
        $teacherId = (int) $this->teacher()->id;
        $attempts = DB::transaction(function () use ($test, $data, $teacherId): Collection {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $selected = $locked->attempts()->whereIn('id', $data['attempt_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($selected->count() !== count($data['attempt_ids'])) {
                throw ValidationException::withMessages(['attempt_ids' => ['Die Auswahl enthält Personen, die nicht zu diesem Test gehören.']]);
            }

            return $selected->map(fn (McAttempt $attempt): McAttempt => $this->tests->reopenAttempt($attempt, $data['remaining_seconds'], $teacherId));
        }, 3);
        $lastEnd = $attempts->sortByDesc(fn (McAttempt $attempt): int => $attempt->reopened_until->getTimestamp())->first()->reopened_until;
        FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($lastEnd)->afterCommit();

        return response()->json(['test' => $test->fresh(), 'attempts' => $this->attemptPayloads($test), 'reopened' => $attempts->count(), 'server_now' => now()->toIso8601String()]);
    }

    public function reopenAttempt(Request $request, McTest $test, McAttempt $attempt): JsonResponse
    {
        $this->authorizeOwned($test);
        abort_unless((int) $attempt->test_id === (int) $test->id, 404);
        abort_unless(Schema::hasColumns('mc_attempts', ['reopened_until', 'reopen_history']), 503, 'Das individuelle Wiederöffnen ist noch nicht eingerichtet.');
        $data = $request->validate(['remaining_seconds' => ['required', 'integer', 'min:1', 'max:86400']]);
        $attempt = $this->tests->reopenAttempt($attempt, $data['remaining_seconds'], (int) $this->teacher()->id);
        FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($attempt->reopened_until)->afterCommit();

        return response()->json(['test' => $test->fresh(), 'attempts' => $this->attemptPayloads($test), 'server_now' => now()->toIso8601String()]);
    }

    public function changeClosingDeadline(Request $request, McTest $test, McAttempt $attempt): JsonResponse
    {
        $this->authorizeOwned($test);
        abort_unless((int) $attempt->test_id === (int) $test->id, 404);
        $data = $request->validate(['closes_at' => ['present', 'nullable', 'date', 'after:now']]);
        $attempt = $this->tests->changeClosingDeadline($attempt, $data['closes_at']);
        FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($attempt->effectiveDeadline($test))->afterCommit();

        return response()->json(['attempts' => $this->attemptPayloads($test)]);
    }

    public function showTest(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);

        return response()->json(['test' => $test, 'attempts' => $this->attemptPayloads($test), 'can_edit_settings' => $test->status === 'published' && $test->paused_at === null && $test->starts_at === null && ! $test->attempts()->where(fn ($query) => $query->whereNotNull('started_at')->orWhereNotNull('completed_at'))->exists(), 'question_pool_count' => count($test->snapshot['questions'] ?? []), 'can_reopen_attempts' => Schema::hasColumns('mc_attempts', ['reopened_until', 'reopen_history']), 'server_now' => now()->toIso8601String()])->header('Cache-Control', 'private, no-store');
    }

    public function countdown(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $test = $this->tests->startCountdown($test);
        FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($test->ends_at)->afterCommit();

        return response()->json(['test' => $test, 'server_now' => now()->toIso8601String()]);
    }

    public function exportExercise(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $teacher = $this->teacher();
        abort_unless($test->schoolyear_id !== null && (int) $test->schoolyear_id === (int) $teacher->schoolyear_id, 404);

        if ($test->status !== 'published' || $test->access_links_disabled_at !== null) {
            throw ValidationException::withMessages(['test' => 'Für den Export müssen der Test freigegeben und seine persönlichen Zugangslinks aktiviert sein.']);
        }

        $attempts = $test->attempts()->with('student:id,school_id,first_name,last_name,email')->orderBy('student_id')->get();
        if ($attempts->isEmpty()) {
            throw ValidationException::withMessages(['participants' => 'Für diesen Test sind keine Teilnehmer zugeordnet.']);
        }

        $participants = $attempts->map(function (McAttempt $attempt) use ($test): array {
            $student = $attempt->student;
            $email = trim((string) $attempt->email);
            if ($student === null || (int) $student->school_id !== (int) $test->school_id
                || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strcasecmp($email, trim((string) $student->email)) !== 0) {
                throw ValidationException::withMessages(['participants' => 'Die Teilnehmerkonten oder E-Mail-Adressen stimmen nicht mehr mit der Testzuordnung überein. Bitte prüfen Sie die Teilnehmer vor dem Export.']);
            }

            return [
                'participant_id' => 'user:'.$attempt->student_id,
                'schooltool_person_id' => (string) $attempt->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $email,
                'attempt_id' => $attempt->id,
                'access_url' => $this->invitationUrl($attempt),
                'closes_at' => $attempt->closingDeadline($test)?->utc()->toIso8601String(),
            ];
        });
        if ($participants->pluck('participant_id')->unique()->count() !== $participants->count()
            || $participants->map(fn (array $participant): string => strtolower($participant['email']))->unique()->count() !== $participants->count()) {
            throw ValidationException::withMessages(['participants' => 'Die Teilnehmer oder E-Mail-Adressen sind nicht eindeutig zugeordnet.']);
        }

        $filename = 'MC-Test_'.(Str::slug($test->title) ?: 'test').'_'.now()->timezone('Europe/Vienna')->format('Y-m-d').'_Nr-'.$test->id.'.json';

        return response()->json([
            'schema_version' => 1,
            'kind' => 'schooltool.multiple_choice_links',
            'exported_at' => now()->utc()->toIso8601String(),
            'timezone' => 'Europe/Vienna',
            'source' => [
                'base_url' => url('/'),
                'environment' => app()->environment(),
                'is_preview' => app(FeaturePreviewService::class)->isPreview(),
                'school_id' => (int) $test->school_id,
                'schoolyear_id' => (int) $test->schoolyear_id,
                'teacher_id' => (int) $test->owner_id,
            ],
            'test' => [
                'id' => $test->id,
                'title' => $test->title,
                'catalog_id' => $test->catalog_id,
                'status' => $test->status,
                'question_count' => $test->question_count,
                'total_points' => $test->total_points,
                'duration_seconds' => $test->duration_seconds,
                'lead_seconds' => $test->lead_seconds,
                'start_mode' => $test->start_mode ?? 'common',
                'starts_at' => $test->starts_at?->utc()->toIso8601String(),
                'ends_at' => $test->ends_at?->utc()->toIso8601String(),
                'closes_at' => $test->closes_at?->utc()->toIso8601String(),
            ],
            'participants' => $participants->all(),
        ], 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function invitations(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        if ($test->access_links_disabled_at !== null) {
            throw ValidationException::withMessages(['access_links' => 'Aktivieren Sie zuerst die persönlichen Zugangslinks für diesen Test.']);
        }
        $data = $request->validate(['address_form' => ['sometimes', 'required', 'in:sie,du']]);
        $addressForm = $data['address_form'] ?? 'sie';
        $recipients = $test->attempts()->with('student')->orderBy('student_name')->get()->map(function (McAttempt $attempt) use ($test, $addressForm): array {
            $url = $this->invitationUrl($attempt);
            $mail = $this->invitationMail($test, $attempt, $addressForm);

            return [
                'attempt_id' => $attempt->id,
                'student_name' => $attempt->student_name,
                'email' => $attempt->email,
                'url' => $url,
                'invited_at' => $attempt->invited_at,
                'subject' => $mail->envelope()->subject,
                'html' => $mail->render(),
                'body' => $mail->messageText(),
            ];
        });

        return response()->json(['test_id' => $test->id, 'address_form' => $addressForm, 'recipients' => $recipients, 'count' => $recipients->count()])->header('Cache-Control', 'private, no-store');
    }

    public function sendInvitations(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        if (app(FeaturePreviewService::class)->isPreview()) {
            throw ValidationException::withMessages(['mail' => ['In dieser geschützten Vorschau ist der Versand von Testeinladungen gesperrt. Die Empfängervorschau bleibt verfügbar.']]);
        }
        $data = $request->validate([
            'address_form' => ['sometimes', 'required', 'in:sie,du'],
            'attempt_ids' => ['required', 'array', 'min:1'],
            'attempt_ids.*' => ['required', 'integer', 'distinct'],
            'messages' => ['sometimes', 'array', 'max:2000'],
            'messages.*' => ['array:attempt_id,subject,body'],
            'messages.*.attempt_id' => ['required', 'integer', 'distinct'],
            'messages.*.subject' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'messages.*.body' => ['required', 'string', 'max:20000'],
        ]);
        $attemptIds = $test->attempts()->whereIn('id', $data['attempt_ids'])->orderBy('id')->pluck('id');
        if ($attemptIds->count() !== count($data['attempt_ids'])) {
            throw ValidationException::withMessages(['attempt_ids' => ['Die Auswahl enthält Personen, die nicht zu diesem Test gehören.']]);
        }
        $messages = collect($data['messages'] ?? [])->keyBy('attempt_id');
        if ($messages->keys()->diff($attemptIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['messages' => ['Nachrichten dürfen nur für die ausgewählten Personen dieses Tests bearbeitet werden.']]);
        }
        $addressForm = $data['address_form'] ?? 'sie';
        $sent = 0;
        $skipped = 0;
        foreach ($attemptIds as $attemptId) {
            $wasSent = DB::transaction(function () use ($attemptId, $test, $addressForm, $messages): bool {
                $lockedTest = McTest::query()->lockForUpdate()->findOrFail($test->id);
                if ($lockedTest->status !== 'published' || $lockedTest->access_links_disabled_at !== null || ($lockedTest->start_mode !== 'individual' && $lockedTest->ends_at !== null && $lockedTest->ends_at->isPast())) {
                    throw ValidationException::withMessages(['test' => ['Einladungen können nur für einen freigegebenen, noch nicht beendeten Test versendet werden.']]);
                }
                $attempt = McAttempt::query()->where('test_id', $lockedTest->id)->lockForUpdate()->findOrFail($attemptId);
                if ($lockedTest->start_mode === 'individual' && ! $attempt->closingDeadline($lockedTest)?->isFuture()) {
                    throw ValidationException::withMessages(['closes_at' => ['Die Schlussfrist dieser Person ist abgelaufen. Bitte passen Sie die Frist vor einer Einladung an.']]);
                }
                if ($attempt->invited_at !== null) {
                    return false;
                }

                Mail::to($attempt->email)->send($this->invitationMail($lockedTest, $attempt, $addressForm, $messages->get($attemptId)));
                $attempt->update(['invited_at' => now()]);

                return true;
            });
            $wasSent ? $sent++ : $skipped++;
        }

        return response()->json([
            'sent' => $sent,
            'skipped' => $skipped,
            'message' => 'Versand an den konfigurierten Mailtransport übergeben. Dies ist kein Zustell- oder Lesenachweis.',
        ]);
    }

    public function releaseResults(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['solutions' => ['required', 'boolean']]);
        DB::transaction(function () use ($test, $data): void {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            if ($locked->status !== 'published' || ! $locked->attempts()->whereNotNull('completed_at')->exists()) {
                throw ValidationException::withMessages(['test' => ['Ergebnisse können freigegeben werden, sobald mindestens eine Abgabe abgeschlossen ist.']]);
            }
            if ($data['solutions']) {
                if ($locked->results_released_at === null) {
                    throw ValidationException::withMessages(['solutions' => ['Bitte geben Sie zuerst die Ergebnisse frei.']]);
                }
                $locked->solutions_released_at ??= now();
            } else {
                $locked->results_released_at ??= now();
            }
            $locked->save();
        });

        return response()->json(['test' => $test->fresh()]);
    }

    public function revokeRelease(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $data = $request->validate(['scope' => ['required', 'in:results,solutions']]);
        DB::transaction(function () use ($test, $data): void {
            $locked = McTest::query()->lockForUpdate()->findOrFail($test->id);
            $locked->solutions_released_at = null;
            if ($data['scope'] === 'results') {
                $locked->results_released_at = null;
            }
            $locked->save();
        });

        return response()->json(['test' => $test->fresh()]);
    }

    private function saveCatalog(Request $request, McCatalog $catalog): JsonResponse
    {
        $sync = app(McCatalogTemplateSync::class);
        $request->validate(['expected_version' => ['sometimes', 'nullable', 'string', 'size:64']]);

        return $sync->serialize($catalog, function () use ($request, $catalog, $sync): JsonResponse {
            if ($catalog->exists) {
                $catalog->refresh();
                $sync->assertEditable($catalog, $request->input('expected_version'));
            }

            return $this->saveCatalogContents($request, $catalog);
        });
    }

    private function saveCatalogContents(Request $request, McCatalog $catalog): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'questions' => ['present', 'array', 'max:2000'],
            'questions.*' => ['array:id,prompt,type,options,correct_option_ids,active,reviewed'],
            'questions.*.id' => ['required', 'string', 'max:100', 'distinct'],
            'questions.*.prompt' => ['present', 'nullable', 'string', 'max:20000'],
            'questions.*.type' => ['required', Rule::in(['single', 'multiple'])],
            'questions.*.options' => ['present', 'array', 'max:30'],
            'questions.*.options.*' => ['array:id,text'],
            'questions.*.options.*.id' => ['required', 'string', 'max:100'],
            'questions.*.options.*.text' => ['present', 'nullable', 'string', 'max:10000'],
            'questions.*.correct_option_ids' => ['present', 'array', 'max:30'],
            'questions.*.correct_option_ids.*' => ['string', 'max:100'],
            'questions.*.active' => ['required', 'boolean'],
            'questions.*.reviewed' => ['required', 'boolean'],
            'module_source_proofs' => ['sometimes', 'array', 'max:20'],
            'module_source_proofs.*' => ['required', 'string', 'max:8192'],
        ]);
        $created = ! $catalog->exists;
        if (($data['module_source_proofs'] ?? []) !== []) {
            app(McCatalogTemplateSync::class)->assertCanImport($catalog);
            abort_unless(Schema::hasColumn('mc_catalogs', 'module_sources'), 409, 'Die lokale Migration für Modulquellen wurde noch nicht ausgeführt.');
            $sources = $catalog->module_sources ?? [];
            foreach ($data['module_source_proofs'] as $proof) {
                $sources[] = app(McModuleTemplateSource::class)->verify($this->teacher(), $created ? null : (int) $catalog->id, $proof);
            }
            $catalog->module_sources = collect($sources)->unique(fn (array $source): string => $source['source_file_id'].':'.$source['source_revision'])->values()->all();
        }
        $catalog->fill(['title' => trim($data['title']), 'questions' => $data['questions']])->save();

        return response()->json(['catalog' => app(McCatalogTemplateSync::class)->payload($catalog), 'errors' => $this->questions->errors($data['questions']), 'warnings' => []], $created ? 201 : 200);
    }

    private function teacher(): User
    {
        $user = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($user && $user->school_id, 403, 'Sie haben keine Berechtigung.');

        return $user;
    }

    private function authorizeOwned(McCatalog|McTest $model): void
    {
        $user = $this->teacher();
        abort_unless((int) $model->school_id === (int) $user->school_id && (int) $model->owner_id === (int) $user->id, 404);
    }

    private function invitationMail(McTest $test, McAttempt $attempt, string $addressForm, ?array $message = null): MultipleChoiceInvitation
    {
        $attempt->loadMissing('student');

        return new MultipleChoiceInvitation(
            $test->title, $attempt->student_name, $this->invitationUrl($attempt),
            $addressForm, (string) $attempt->student?->first_name,
            (string) $attempt->student?->last_name, (string) $attempt->student?->sex,
            $message['subject'] ?? null, $message['body'] ?? null,
            $test->start_mode ?? 'common', $attempt->closingDeadline($test)?->timezone(config('app.timezone'))->format('d.m.Y H:i'), $test->duration_seconds,
        );
    }

    private function invitationUrl(McAttempt $attempt): string
    {
        return url('/multiple-choice').'#access='.rawurlencode($attempt->access_token);
    }

    /** @return array<int, array<string, mixed>> */
    private function attemptPayloads(McTest $test): array
    {
        return $test->attempts()->orderBy('student_name')->get()->map(function (McAttempt $attempt) use ($test): array {
            $status = 'not_started';
            if ($attempt->ready_at !== null) {
                $status = 'ready';
            }
            if ($attempt->started_at !== null || ($test->start_mode !== 'individual' && $attempt->ready_at !== null && $test->starts_at?->isPast())) {
                $status = 'in_progress';
            }
            if ($attempt->completed_at !== null) {
                $status = $attempt->completion_reason === 'submitted' ? 'submitted' : 'timeout';
            }
            if ($attempt->started_at === null && $attempt->ready_at === null && $attempt->submitted_at === null) {
                $status = 'not_started';
            }
            $questions = $attempt->questions ?? [];
            $answers = $attempt->answers ?? [];
            $evaluation = array_map(function (array $question) use ($attempt, $answers, $test): array {
                $selected = array_values($answers[$question['id']] ?? []);
                $correct = array_values($question['correct_option_ids'] ?? []);
                $sortedSelected = $selected;
                $sortedCorrect = $correct;
                sort($sortedSelected);
                sort($sortedCorrect);

                $isCorrect = $attempt->completed_at !== null ? $sortedSelected === $sortedCorrect : null;
                $maximum = (float) $test->total_points / $test->question_count;

                return [
                    ...$question,
                    'selected_option_ids' => $selected,
                    'correct_option_ids' => $correct,
                    'correct' => $isCorrect,
                    'points' => $isCorrect === null ? null : ($isCorrect ? $maximum : 0),
                    'max_points' => $maximum,
                ];
            }, $questions);

            return [...$attempt->toArray(), 'activity' => ($test->exam_mode || $attempt->activity_state !== null) ? app(McAttemptActivityService::class)->summary($attempt, $test) : null, 'effective_ends_at' => $attempt->effectiveDeadline($test)?->toIso8601String(), 'closing_deadline' => $attempt->closingDeadline($test)?->toIso8601String(), 'access_url' => $test->status === 'published' && $test->access_links_disabled_at === null ? $this->invitationUrl($attempt) : null, 'status' => $status, 'questions' => $questions, 'answers' => $answers, 'evaluation' => $evaluation, 'question_results' => $evaluation];
        })->all();
    }
}
