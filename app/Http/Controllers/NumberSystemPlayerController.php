<?php

namespace App\Http\Controllers;

use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\User;
use App\Services\LicenceService;
use App\Services\NumberSystems\NumberSystemGameService;
use App\Services\ParentStudentAccessService;
use App\Services\SchoolToolModuleStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class NumberSystemPlayerController extends Controller
{
    public function __construct(private NumberSystemGameService $games) {}

    public function index(Request $request): JsonResponse
    {
        $student = $this->student();
        $runs = NumberSystemGameRun::query()->with('session')->whereHas('members', fn ($query) => $query->where('users.id', $student->id))
            ->whereHas('session', fn ($query) => $query->where('school_id', $student->school_id))
            ->whereHas('session.course.teachingCourseStudents', fn ($query) => $query->where('user_id', $student->id)->whereNull('canceled_at'))
            ->latest('id')->get()->map(fn (NumberSystemGameRun $run): array => ['id' => $run->id, 'title' => $run->session->title,
                'status' => $run->session->status, 'completed' => $run->completed_at !== null, 'solved' => count($run->history ?? []),
                'total' => count($run->puzzles), 'team_name' => $run->team_name])->values();

        return $this->respond(['runs' => $runs]);
    }

    public function join(Request $request): JsonResponse
    {
        $student = $this->student();
        $data = $request->validate(['code' => ['required', 'string', 'max:20'], 'team_name' => ['nullable', 'string', 'max:60'],
            'team_code' => ['nullable', 'string', 'max:12']]);
        $run = $this->games->join($student, $data['code'], $data['team_name'] ?? null, $data['team_code'] ?? null);

        return $this->respond(['run' => $this->games->state($run, $student)]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $student = $this->student();
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $session = NumberSystemGameSession::query()->where('join_code', strtoupper(trim($data['code'])))->firstOrFail();
        $this->games->assertEnrolled($student, $session);

        return $this->respond(['session' => ['title' => $session->title, 'mode' => $session->mode, 'status' => $session->status]]);
    }

    public function show(Request $request, NumberSystemGameRun $run): JsonResponse
    {
        $student = $this->student();
        $this->games->authorizeRun($student, $run);

        return $this->respond(['run' => $this->games->state($run, $student)]);
    }

    public function update(Request $request, NumberSystemGameRun $run): JsonResponse
    {
        $student = $this->student();
        $this->games->authorizeRun($student, $run);
        $data = $request->validate(['action' => ['required', 'in:draft,answer,hint,advance'], 'revision' => ['required', 'integer', 'min:0'],
            'mission_id' => ['required', 'string', 'size:24'], 'draft' => ['sometimes', 'array', 'max:15'],
            'draft.*' => ['nullable']]);
        if (in_array($data['action'], ['draft', 'answer'], true)) {
            abort_unless(array_key_exists('draft', $data), 422, 'Der Eingabeentwurf fehlt.');
        }

        return $this->respond(['run' => $this->games->mutate($run, $student, $data['action'], $data)]);
    }

    private function student(): User
    {
        $student = Auth::guard('web')->user();
        abort_unless($student instanceof User && app(ParentStudentAccessService::class)->isActiveStudentUser($student), 401, 'Bitte melde dich mit deinem bestehenden Schülerkonto an.');
        abort_if(app(ParentStudentAccessService::class)->isParentViewer(), 403);
        $school = $student->selectedSchool;
        $modules = app(SchoolToolModuleStatusService::class);
        abort_unless($modules->allowsUserAccess($modules->userStatusForModule('teaching', $school)), 403);
        abort_unless(app(LicenceService::class)->toolAccessStatusForUser($student, $school, 'Lehrertool', ['student']) === 'active', 403);
        abort_unless(Schema::hasTable('number_system_game_runs'), 503, 'Das Zahlensysteme-Spiel ist noch nicht aktiviert.');

        return $student;
    }

    private function respond(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
