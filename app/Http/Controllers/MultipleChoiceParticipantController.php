<?php

namespace App\Http\Controllers;

use App\Models\McAttempt;
use App\Models\User;
use App\Services\LicenceService;
use App\Services\MultipleChoice\McTestService;
use App\Services\ParentStudentAccessService;
use App\Services\SchoolToolModuleStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MultipleChoiceParticipantController extends Controller
{
    public function __construct(private McTestService $tests) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertAvailable();
        $student = $this->accountStudent();
        $this->assertLicensed($student);
        $attempts = McAttempt::query()->with('test')
            ->where('student_id', $student->id)
            ->whereHas('test', fn ($query) => $query->where('school_id', $student->school_id)->where('status', 'published'))
            ->latest('id')->get();

        return $this->respond(['attempts' => $attempts->map(fn (McAttempt $attempt): array => $this->serialize($attempt, false))->all()]);
    }

    public function access(Request $request): JsonResponse
    {
        $this->assertAvailable();
        $token = $this->token($request);
        abort_if($token === null, 404);
        $attempt = McAttempt::query()->where('access_token_hash', hash('sha256', $token))->firstOrFail();

        return $this->show($request, $attempt);
    }

    public function show(Request $request, McAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $this->tests->finalizeExpired((int) $attempt->test_id);
        $attempt = $this->tests->openAttempt($attempt->fresh());

        return $this->respond(['attempt' => $this->serialize($attempt->load('test'))]);
    }

    public function ready(Request $request, McAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $attempt = $this->tests->markReady($attempt);

        return $this->respond(['attempt' => $this->serialize($attempt->load('test'))]);
    }

    public function save(Request $request, McAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $data = $request->validate(['answers' => ['required', 'array', 'max:1000'], 'answers.*' => ['array', 'max:100']]);
        $attempt = $this->tests->saveAnswers($attempt, $data['answers']);

        return $this->respond(['attempt' => $this->serialize($attempt->load('test'))]);
    }

    public function submit(Request $request, McAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $attempt = $this->tests->submit($attempt);

        return $this->respond(['attempt' => $this->serialize($attempt->load('test'))]);
    }

    private function assertAvailable(): void
    {
        abort_unless(Schema::hasTable('mc_attempts'), 503, 'Multiple Choice ist noch nicht aktiviert. Bitte wende dich an deine Lehrkraft.');
    }

    private function accountStudent(): User
    {
        $student = Auth::guard('web')->user();
        abort_unless($student instanceof User && app(ParentStudentAccessService::class)->isActiveStudentUser($student), 401, 'Bitte melde dich mit deinem Schülerkonto an.');
        abort_if(app(ParentStudentAccessService::class)->isParentViewer(), 403, 'Tests sind nur mit dem persönlichen Schülerzugang möglich.');

        return $student;
    }

    private function authorizeAttempt(Request $request, McAttempt $attempt): void
    {
        $this->assertAvailable();
        $attempt->loadMissing(['test', 'student']);
        $student = $attempt->student;
        abort_unless($student && app(ParentStudentAccessService::class)->isActiveStudentUser($student), 404);
        abort_unless($attempt->test && $attempt->test->status === 'published' && (int) $attempt->test->school_id === (int) $student->school_id, 404);

        $token = $this->token($request);
        if ($token !== null) {
            abort_unless(hash_equals((string) $attempt->access_token_hash, hash('sha256', $token)), 404);
            $account = Auth::guard('web')->user();
            abort_if($account && (int) $account->id !== (int) $student->id, 403, 'Dieser Link gehört zu einem anderen Schülerkonto. Bitte melde das andere Konto zuerst ab.');
            abort_if(app(ParentStudentAccessService::class)->isParentViewer(), 403);
        } else {
            $account = $this->accountStudent();
            abort_unless((int) $account->id === (int) $student->id && (int) $account->school_id === (int) $attempt->test->school_id, 404);
        }

        $this->assertLicensed($student);
    }

    private function assertLicensed(User $student): void
    {
        $school = $student->selectedSchool;
        $modules = app(SchoolToolModuleStatusService::class);
        abort_unless($modules->allowsUserAccess($modules->userStatusForModule('teaching', $school)), 403, 'Das Unterrichtstool ist derzeit nicht freigegeben.');
        abort_unless(app(LicenceService::class)->toolAccessStatusForUser($student, $school, 'Lehrertool', ['student']) === 'active', 403, 'Der Unterrichtszugang ist derzeit nicht freigegeben.');
    }

    private function token(Request $request): ?string
    {
        if (! $request->hasHeader('X-MC-Access')) {
            return null;
        }

        $token = (string) $request->header('X-MC-Access');
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $token) === 1, 404);

        return $token;
    }

    /** @return array<string, mixed> */
    private function serialize(McAttempt $attempt, bool $includeQuestions = true): array
    {
        $test = $attempt->test;
        $released = $test->starts_at !== null && now()->greaterThanOrEqualTo($test->starts_at);
        $status = $attempt->completed_at
            ? ($attempt->completion_reason === 'submitted' ? 'submitted' : 'timeout')
            : ($attempt->started_at ? 'in_progress' : ($attempt->ready_at ? 'ready' : 'not_started'));
        $questions = $released && $includeQuestions ? $attempt->questions : [];
        $result = null;
        if ($attempt->completed_at && $test->results_released_at) {
            $result = ['score' => $attempt->score, 'percentage' => $attempt->percentage, 'correct_count' => $attempt->correct_count];
            if ($test->solutions_released_at && $includeQuestions) {
                $result['question_results'] = array_map(function (array $question) use ($attempt): array {
                    $selected = array_values($attempt->answers[$question['id']] ?? []);
                    $correct = array_values($question['correct_option_ids']);
                    sort($selected);
                    sort($correct);

                    return ['question_id' => $question['id'], 'correct' => $selected === $correct,
                        'selected_option_ids' => $selected, 'correct_option_ids' => $correct];
                }, $attempt->questions);
            }
        }

        return [
            'id' => $attempt->id, 'test_id' => $test->id, 'title' => $test->title,
            'question_count' => $test->question_count, 'total_points' => $test->total_points,
            'duration_seconds' => $test->duration_seconds, 'lead_seconds' => $test->lead_seconds,
            'starts_at' => $test->starts_at?->toIso8601String(), 'ends_at' => $test->ends_at?->toIso8601String(),
            'server_now' => now()->toIso8601String(), 'status' => $status,
            'ready_at' => $attempt->ready_at?->toIso8601String(), 'started_at' => $attempt->started_at?->toIso8601String(),
            'completed_at' => $attempt->completed_at?->toIso8601String(),
            'answers' => $released && $includeQuestions ? (object) ($attempt->answers ?? []) : (object) [],
            'questions' => array_map(fn (array $question): array => [
                'id' => $question['id'], 'prompt' => $question['prompt'], 'type' => $question['type'],
                'options' => array_map(fn (array $option): array => ['id' => $option['id'], 'text' => $option['text']], $question['options']),
            ], $questions),
            'result' => $result,
        ];
    }

    /** @param array<string, mixed> $data */
    private function respond(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
