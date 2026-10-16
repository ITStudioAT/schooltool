<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FinalizeMultipleChoiceTest;
use App\Mail\MultipleChoiceInvitation;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\User;
use App\Services\FeaturePreviewService;
use App\Services\MultipleChoice\McImportService;
use App\Services\MultipleChoice\McQuestionValidator;
use App\Services\MultipleChoice\McTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MultipleChoiceController extends Controller
{
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
            'catalogs' => McCatalog::query()->where('school_id', $user->school_id)->where('owner_id', $user->id)->orderBy('title')->get(),
            'tests' => McTest::query()->where('school_id', $user->school_id)->where('owner_id', $user->id)->withCount('attempts')->latest('id')->get(),
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
        $this->teacher();
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

    public function rosterPreview(Request $request): JsonResponse
    {
        $user = $this->teacher();
        $data = $request->validate(['csv' => ['required', 'string', 'max:1048576']]);

        return response()->json($this->imports->rosterPreview($data['csv'], (int) $user->school_id));
    }

    public function createTest(Request $request): JsonResponse
    {
        $user = $this->teacher();
        $data = $request->validate([
            'catalog_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'question_count' => ['required', 'integer', 'min:1', 'max:2000'],
            'total_points' => ['required', 'numeric', 'min:0.01', 'max:100000', 'decimal:0,2'],
            'duration_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'lead_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'balance_types' => ['required', 'boolean'],
            'shuffle_questions' => ['required', 'boolean'],
            'shuffle_options' => ['required', 'boolean'],
            'roster_csv' => ['required', 'string', 'max:1048576'],
        ]);
        $catalog = McCatalog::query()->findOrFail($data['catalog_id']);
        $this->authorizeOwned($catalog);
        $roster = $this->imports->rosterPreview($data['roster_csv'], (int) $user->school_id);
        if (! ($roster['valid'] ?? false) || empty($roster['rows'])) {
            throw ValidationException::withMessages(['roster_csv' => ['Die gesamte Schülerliste muss eindeutig und fehlerfrei zugeordnet sein. Bitte prüfen Sie die Vorschau.']]);
        }

        $studentIds = [];
        foreach ($roster['rows'] as $row) {
            if (($row['status'] ?? null) !== 'matched' || empty($row['user_id']) || ! empty($row['errors'])) {
                throw ValidationException::withMessages(['roster_csv' => ['Mindestens eine Zeile der Schülerliste ist nicht eindeutig zugeordnet.']]);
            }
            $studentIds[] = (int) $row['user_id'];
        }

        $test = DB::transaction(function () use ($data, $catalog, $user, $studentIds): McTest {
            $test = McTest::query()->create([
                'school_id' => $user->school_id,
                'owner_id' => $user->id,
                'catalog_id' => $catalog->id,
                'title' => trim($data['title']),
                'status' => 'draft',
                'question_count' => $data['question_count'],
                'total_points' => $data['total_points'],
                'duration_seconds' => $data['duration_seconds'],
                'lead_seconds' => $data['lead_seconds'],
                'balance_types' => $data['balance_types'],
                'shuffle_questions' => $data['shuffle_questions'],
                'shuffle_options' => $data['shuffle_options'],
            ]);

            return $this->tests->publish($test, $studentIds);
        });

        return response()->json(['test' => $test, 'attempts' => $this->attemptPayloads($test)], 201);
    }

    public function showTest(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);

        return response()->json(['test' => $test, 'attempts' => $this->attemptPayloads($test), 'server_now' => now()->toIso8601String()]);
    }

    public function countdown(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $test = $this->tests->startCountdown($test);
        FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($test->ends_at)->afterCommit();

        return response()->json(['test' => $test, 'server_now' => now()->toIso8601String()]);
    }

    public function invitations(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        $recipients = $test->attempts()->orderBy('student_name')->get()->map(fn (McAttempt $attempt): array => [
            'attempt_id' => $attempt->id,
            'student_name' => $attempt->student_name,
            'email' => $attempt->email,
            'url' => $this->invitationUrl($attempt),
            'invited_at' => $attempt->invited_at,
        ]);

        return response()->json(['test_id' => $test->id, 'recipients' => $recipients, 'count' => $recipients->count()])->header('Cache-Control', 'private, no-store');
    }

    public function sendInvitations(Request $request, McTest $test): JsonResponse
    {
        $this->authorizeOwned($test);
        if (app(FeaturePreviewService::class)->isPreview()) {
            throw ValidationException::withMessages(['mail' => ['In dieser geschützten Vorschau ist der Versand von Testeinladungen gesperrt. Die Empfängervorschau bleibt verfügbar.']]);
        }
        $sent = 0;
        $skipped = 0;
        foreach ($test->attempts()->orderBy('id')->pluck('id') as $attemptId) {
            $wasSent = DB::transaction(function () use ($attemptId, $test): bool {
                $lockedTest = McTest::query()->lockForUpdate()->findOrFail($test->id);
                if ($lockedTest->status !== 'published' || ($lockedTest->ends_at !== null && $lockedTest->ends_at->isPast())) {
                    throw ValidationException::withMessages(['test' => ['Einladungen können nur für einen freigegebenen, noch nicht beendeten Test versendet werden.']]);
                }
                $attempt = McAttempt::query()->where('test_id', $lockedTest->id)->lockForUpdate()->findOrFail($attemptId);
                if ($attempt->invited_at !== null) {
                    return false;
                }

                Mail::to($attempt->email)->send(new MultipleChoiceInvitation($lockedTest->title, $attempt->student_name, $this->invitationUrl($attempt)));
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
            if ($locked->starts_at === null || $locked->starts_at->isFuture()) {
                throw ValidationException::withMessages(['test' => ['Ergebnisse können erst nach dem Test freigegeben werden.']]);
            }
            if ($locked->ends_at->isFuture() && $locked->attempts()->whereNull('completed_at')->exists()) {
                throw ValidationException::withMessages(['test' => ['Der Test läuft noch. Die Ergebnisfreigabe ist nach Testende oder der Abgabe aller Teilnehmer möglich.']]);
            }
            $locked->results_released_at ??= now();
            if ($data['solutions']) {
                $locked->solutions_released_at ??= now();
            }
            $locked->save();
        });

        return response()->json(['test' => $test->fresh()]);
    }

    private function saveCatalog(Request $request, McCatalog $catalog): JsonResponse
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
        ]);
        $created = ! $catalog->exists;
        $catalog->fill(['title' => trim($data['title']), 'questions' => $data['questions']])->save();

        return response()->json(['catalog' => $catalog, 'errors' => $this->questions->errors($data['questions']), 'warnings' => []], $created ? 201 : 200);
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
            if ($attempt->started_at !== null || ($attempt->ready_at !== null && $test->starts_at?->isPast())) {
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

            return [...$attempt->toArray(), 'status' => $status, 'questions' => $questions, 'answers' => $answers, 'evaluation' => $evaluation, 'question_results' => $evaluation];
        })->all();
    }
}
