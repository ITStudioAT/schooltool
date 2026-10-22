<?php

namespace App\Http\Controllers;

use App\Models\NumberSystemGameGuest;
use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\School;
use App\Models\User;
use App\Services\LicenceService;
use App\Services\NumberSystems\NumberSystemGameService;
use App\Services\NumberSystems\NumberSystemPresence;
use App\Services\ParentStudentAccessService;
use App\Services\SchoolToolModuleStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NumberSystemPlayerController extends Controller
{
    public function __construct(private NumberSystemGameService $games) {}

    public function index(Request $request): JsonResponse
    {
        if (! Auth::guard('web')->check()) {
            $token = $request->session()->get('number_systems.guest_token');
            $runs = Schema::hasTable('number_system_game_guests') && is_string($token)
                ? NumberSystemGameRun::query()->with('session')->whereHas('guests', fn ($query) => $query->where('token_hash', hash('sha256', $token)))
                    ->whereHas('session', fn ($query) => $query->whereIn('audience', ['mixed', 'external']))->latest('id')->get()
                : collect();

            return $this->respond(['guest' => true, 'runs' => $runs->map(fn (NumberSystemGameRun $run): array => [
                'id' => $run->id, 'title' => $run->session->title, 'status' => $run->session->status,
                'completed' => $run->completed_at !== null, 'solved' => count($run->history ?? []),
                'total' => count($run->puzzles), 'team_name' => $run->team_name,
            ])->values()]);
        }
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
        $data = $request->validate(['code' => ['required', 'string', 'max:20'], 'team_name' => ['nullable', 'string', 'max:60'],
            'team_code' => ['nullable', 'string', 'max:12'], 'display_name' => ['nullable', 'string', 'max:60']]);
        if (! Auth::guard('web')->check()) {
            $token = $request->session()->get('number_systems.guest_token');
            if (! is_string($token)) {
                $token = bin2hex(random_bytes(32));
                $request->session()->put('number_systems.guest_token', $token);
            }
            [$run, $student] = DB::transaction(function () use ($request, $data, $token): array {
                $session = NumberSystemGameSession::query()->where('join_code', strtoupper(trim($data['code'])))->lockForUpdate()->firstOrFail();
                $this->assertExternal($session);
                $request->validate(['display_name' => ['required', 'string', 'max:60']]);
                abort_unless(trim($data['display_name'] ?? '') !== '', 422, 'Bitte gib einen Anzeigenamen ein.');
                $guest = NumberSystemGameGuest::query()->firstOrCreate(['session_id' => $session->id, 'token_hash' => hash('sha256', $token)], ['first_name' => trim($data['display_name'])]);

                return [$this->games->join($guest, $data['code'], $data['team_name'] ?? null, $data['team_code'] ?? null), $guest];
            }, 3);
        } else {
            $student = $this->student();
            $run = $this->games->join($student, $data['code'], $data['team_name'] ?? null, $data['team_code'] ?? null);
        }
        app(NumberSystemPresence::class)->record($run, $student);

        return $this->respond(['run' => $this->games->state($run, $student)]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $session = NumberSystemGameSession::query()->where('join_code', strtoupper(trim($data['code'])))->firstOrFail();
        $guest = ! Auth::guard('web')->check();
        if ($guest) {
            $this->assertExternal($session);
        } else {
            $this->games->assertEnrolled($this->student(), $session);
        }

        return $this->respond(['session' => ['title' => $session->title, 'mode' => $session->mode, 'status' => $session->status, 'guest' => $guest]]);
    }

    public function show(Request $request, NumberSystemGameRun $run): JsonResponse
    {
        $student = $this->participant($request, $run);
        app(NumberSystemPresence::class)->record($run, $student);

        return $this->respond(['run' => $this->games->state($run, $student)]);
    }

    public function presence(Request $request, NumberSystemGameRun $run): JsonResponse
    {
        return $this->respond(['recorded' => app(NumberSystemPresence::class)->record($run, $this->participant($request, $run))]);
    }

    public function update(Request $request, NumberSystemGameRun $run): JsonResponse
    {
        $student = $this->participant($request, $run);
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

    private function assertExternal(NumberSystemGameSession $session): void
    {
        abort_unless(in_array($session->audience, ['mixed', 'external'], true), 401, 'Bitte melde dich mit deinem bestehenden Schülerkonto an.');
        $school = School::query()->findOrFail($session->school_id);
        $modules = app(SchoolToolModuleStatusService::class);
        abort_unless($modules->allowsUserAccess($modules->userStatusForModule('teaching', $school)), 403);
        abort_unless(app(LicenceService::class)->toolAccessStatusForUser(null, $school, 'Lehrertool') === 'active', 403);
    }

    private function participant(Request $request, NumberSystemGameRun $run): User|NumberSystemGameGuest
    {
        if (Auth::guard('web')->check()) {
            return $this->student();
        }
        $this->assertExternal($run->session);
        $token = $request->session()->get('number_systems.guest_token');
        abort_unless(is_string($token) && Schema::hasTable('number_system_game_guests'), 404);

        return NumberSystemGameGuest::query()->where('session_id', $run->session_id)->where('run_id', $run->id)->where('token_hash', hash('sha256', $token))->firstOrFail();
    }

    private function respond(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
