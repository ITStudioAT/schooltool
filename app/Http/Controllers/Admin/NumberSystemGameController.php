<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NumberSystemGameGuest;
use App\Models\NumberSystemGameSession;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Services\NumberSystems\NumberSystemEngine;
use App\Services\NumberSystems\NumberSystemGameResults;
use App\Services\NumberSystems\NumberSystemGameService;
use App\Services\NumberSystems\NumberSystemInvitation;
use App\Services\NumberSystems\NumberSystemLocalProbe;
use App\Services\NumberSystems\NumberSystemPresence;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

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
            ->withCount('runs')->latest('id')->get();

        $probes = app(NumberSystemLocalProbe::class);
        $local = $probes->available($request);

        return response()->json(['available' => true, 'external_available' => Schema::hasTable('number_system_game_guests'), 'timing_available' => Schema::hasColumn('number_system_game_sessions', 'timer_mode'), 'courses' => $courses, 'sessions' => $sessions,
            'local_probe_available' => $local, 'local_probes' => $local ? $probes->listing((int) $teacher->id) : []])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request): JsonResponse
    {
        $teacher = $this->teacher();
        abort_unless(Schema::hasTable('number_system_game_sessions'), 503, 'Das Spiel ist noch nicht aktiviert.');
        abort_unless(Schema::hasColumn('number_system_game_sessions', 'timer_mode'), 503, 'Die Erweiterung für Zeitmessung und Reihung muss noch lokal aktiviert werden. Bestehende Runden bleiben erhalten.');
        $data = $request->validate(['title' => ['required', 'string', 'max:120'], 'course_id' => ['exclude_if:audience,external', 'required_unless:audience,external', 'integer'],
            'audience' => ['sometimes', Rule::in(['course', 'mixed', 'external'])],
            'planned_minutes' => ['required', 'integer', Rule::in([30, 40, 60, 75])], 'difficulty' => ['required', 'integer', 'between:1,3'],
            'mode' => ['required', Rule::in(['solo', 'teams'])], ...NumberSystemGameResults::rules()]);
        abort_if(($data['audience'] ?? 'course') !== 'course' && ! Schema::hasTable('number_system_game_guests'), 503, 'Die Erweiterung für externe Teilnehmende muss noch aktiviert werden.');
        if (! Schema::hasColumn('number_system_game_sessions', 'audience')) {
            unset($data['audience']);
        }
        $course = ($data['audience'] ?? 'course') === 'external' ? null : TeachingCourse::query()->findOrFail($data['course_id']);
        $session = $this->games->create($teacher, $course, $data);

        return response()->json(['session' => $session->makeHidden(['seed'])], 201);
    }

    public function show(Request $request, NumberSystemGameSession $session): JsonResponse
    {
        $this->authorizeSession($session);
        $session->load(['runs.members:id,first_name,last_name']);
        if (Schema::hasTable('number_system_game_guests')) {
            $session->load('runs.guests');
        }
        $enrolled = TeachingCourseStudent::query()->where('teaching_course_id', $session->teaching_course_id)
            ->whereNotNull('user_id')->whereNull('canceled_at')->count();
        $results = app(NumberSystemGameResults::class);
        $presence = app(NumberSystemPresence::class);
        $seen = $presence->snapshot($session);
        $runs = $session->runs->map(function ($run) use ($results, $session, $presence, $seen): array {
            $history = $run->history ?? [];

            return ['id' => $run->id, 'team_name' => $run->team_name, 'team_code' => $run->team_code,
                'members' => $run->participants()->map(fn (User|NumberSystemGameGuest $member): string => trim($member->first_name.' '.$member->last_name))->values()->all(),
                'course_members' => $run->members->count(), 'external_members' => $run->participants()->count() - $run->members->count(),
                'presence' => $presence->members($run, $seen),
                'solved' => count($history), 'total' => count($run->puzzles), 'completed' => $run->completed_at !== null, 'clock' => $results->clock($session, $run),
                'chapter' => $run->puzzles[$run->position]['chapter_title'], 'updated_at' => $run->updated_at->toIso8601String(),
                'with_help' => count(array_filter($history, fn (array $item): bool => $item['hints'] > 0))];
        });

        return response()->json(['session' => $session->withoutRelations(), 'runs' => $runs, 'enrolled' => $enrolled,
            'clock' => $results->clock($session), 'ranking' => $results->ranking($session),
            'total' => $this->engine->count($session->planned_minutes),
            'invitation' => $session->status === 'ended' ? null : app(NumberSystemInvitation::class)->make($session->join_code)])->header('Cache-Control', 'private, no-store');
    }

    public function resultsPdf(Request $request, NumberSystemGameSession $session): Responsable
    {
        $report = $this->show($request, $session)->getData(true);
        $session->loadMissing('course');
        $filename = 'Signal_Null_Auswertung_'.$session->id.'.pdf';

        return Pdf::view('pdfs.number-system-results', [
            'report' => $report,
            'courseTitle' => $session->course?->title,
            'exportedAt' => now()->timezone('Europe/Vienna')->format('d.m.Y H:i'),
        ])->format(Format::A4)->landscape()->name($filename)
            ->headers(['Cache-Control' => 'private, no-store'])->download($filename);
    }

    public function control(Request $request, NumberSystemGameSession $session): JsonResponse
    {
        $this->authorizeSession($session);
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'pause', 'resume', 'end'])]]);
        $this->games->control($session, $data['action']);

        return $this->show($request, $session->fresh());
    }

    public function remove(Request $request, NumberSystemGameSession $session): JsonResponse
    {
        $this->authorizeSession($session);
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:120']]);
        DB::transaction(function () use ($session, $data): void {
            $locked = NumberSystemGameSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->authorizeSession($locked);
            abort_unless(hash_equals($locked->title, $data['confirmation']), 422, 'Die Bestätigung passt nicht zur aktuellen Runde. Bitte öffne den Löschdialog erneut.');
            abort_unless(in_array($locked->status, ['waiting', 'ended'], true), 409, 'Beende die laufende oder pausierte Runde zuerst. Der Fortschritt bleibt bis dahin erhalten.');
            $locked->delete();
        }, 3);

        return response()->json(['deleted' => true, 'id' => $session->id])->header('Cache-Control', 'private, no-store');
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
