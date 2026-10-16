<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NumberSystemGameSession;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Services\NumberSystems\NumberSystemEngine;
use App\Services\NumberSystems\NumberSystemGameService;
use App\Services\NumberSystems\NumberSystemInvitation;
use App\Services\NumberSystems\NumberSystemLocalProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class NumberSystemGameController extends Controller
{
    public function __construct(private NumberSystemGameService $games, private NumberSystemEngine $engine) {}

    public function index(Request $request): JsonResponse
    {
        $teacher = $this->teacher();
        if (! Schema::hasTable('number_system_game_sessions')) {
            return response()->json(['available' => false, 'courses' => [], 'sessions' => [], 'message' => 'Die Spieltabellen sind noch nicht aktiviert.']);
        }
        $courses = TeachingCourse::query()->where('school_id', $teacher->school_id)->where('user_id', $teacher->id)
            ->when($teacher->schoolyear_id, fn ($query) => $query->where('schoolyear_id', $teacher->schoolyear_id))
            ->orderBy('title')->get(['id', 'title']);
        $sessions = NumberSystemGameSession::query()->where('school_id', $teacher->school_id)->where('teacher_id', $teacher->id)
            ->withCount('runs')->latest('id')->get(['id', 'title', 'status', 'join_code', 'planned_minutes', 'difficulty', 'mode', 'teaching_course_id', 'created_at']);

        $probes = app(NumberSystemLocalProbe::class);
        $local = $probes->available($request);

        return response()->json(['available' => true, 'courses' => $courses, 'sessions' => $sessions,
            'local_probe_available' => $local, 'local_probes' => $local ? $probes->listing((int) $teacher->id) : []])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request): JsonResponse
    {
        $teacher = $this->teacher();
        abort_unless(Schema::hasTable('number_system_game_sessions'), 503, 'Das Spiel ist noch nicht aktiviert.');
        $data = $request->validate(['title' => ['required', 'string', 'max:120'], 'course_id' => ['required', 'integer'],
            'planned_minutes' => ['required', 'integer', Rule::in([30, 40, 60, 75])], 'difficulty' => ['required', 'integer', 'between:1,3'],
            'mode' => ['required', Rule::in(['solo', 'teams'])]]);
        $course = TeachingCourse::query()->findOrFail($data['course_id']);
        $session = $this->games->create($teacher, $course, $data);

        return response()->json(['session' => $session->makeHidden(['seed'])], 201);
    }

    public function show(Request $request, NumberSystemGameSession $session): JsonResponse
    {
        $this->authorizeSession($session);
        $session->load(['runs.members:id,first_name,last_name']);
        $enrolled = TeachingCourseStudent::query()->where('teaching_course_id', $session->teaching_course_id)
            ->whereNotNull('user_id')->whereNull('canceled_at')->count();
        $runs = $session->runs->map(function ($run): array {
            $history = $run->history ?? [];

            return ['id' => $run->id, 'team_name' => $run->team_name, 'team_code' => $run->team_code,
                'members' => $run->members->map(fn (User $member): string => trim($member->first_name.' '.$member->last_name))->values()->all(),
                'solved' => count($history), 'total' => count($run->puzzles), 'completed' => $run->completed_at !== null,
                'chapter' => $run->puzzles[$run->position]['chapter_title'], 'updated_at' => $run->updated_at->toIso8601String(),
                'with_help' => count(array_filter($history, fn (array $item): bool => $item['hints'] > 0))];
        });

        return response()->json(['session' => $session->withoutRelations(), 'runs' => $runs, 'enrolled' => $enrolled,
            'total' => $this->engine->count($session->planned_minutes),
            'invitation' => $session->status === 'ended' ? null : app(NumberSystemInvitation::class)->make($session->join_code)])->header('Cache-Control', 'private, no-store');
    }

    public function control(Request $request, NumberSystemGameSession $session): JsonResponse
    {
        $this->authorizeSession($session);
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'pause', 'resume', 'end'])]]);
        $this->games->control($session, $data['action']);

        return $this->show($request, $session->fresh());
    }

    private function teacher(): User
    {
        $teacher = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($teacher && $teacher->school_id, 403);

        return $teacher;
    }

    private function authorizeSession(NumberSystemGameSession $session): void
    {
        $teacher = $this->teacher();
        abort_unless((int) $session->school_id === (int) $teacher->school_id && (int) $session->teacher_id === (int) $teacher->id, 404);
    }
}
