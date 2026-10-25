<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingSimulation;
use App\Services\TeachingSimulationMailpit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeachingSimulationController extends Controller
{
    public function startMailpit(TeachingSimulationMailpit $mailpit): JsonResponse
    {
        $this->actor();
        $mailpit->start();

        return response()->json(['message' => 'Die lokale Simulator-Mailpit-Instanz ist gestartet und geprüft.']);
    }

    public function index(TeachingSimulation $simulator): JsonResponse
    {
        $actor = $this->actor();
        $works = TeachingCourseWork::query()->whereHas('teachingCourse', fn ($query) => $query
            ->where('school_id', $actor->school_id)->where('schoolyear_id', $actor->schoolyear_id)->where('user_id', $actor->id))
            ->with('teachingCourse:id,title')->orderByDesc('id')->limit(200)->get()->map(fn (TeachingCourseWork $work): array => [
                'id' => $work->id, 'course_id' => $work->teaching_course_id, 'title' => $work->title,
                'course_title' => $work->teachingCourse->title,
                'dates' => array_values(array_unique(array_filter([$work->date_for_all_groups?->format('Y-m-d'),
                    ...array_column(app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work), 'date')]))),
            ])->all();

        return response()->json(['works' => $works, 'runs' => $simulator->listing($actor->id)]);
    }

    public function store(Request $request, TeachingCourseWork $course_work, TeachingSimulation $simulator): JsonResponse
    {
        $actor = $this->actor();
        abort_unless($course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate(['profile' => ['required', Rule::in(['good', 'mixed'])], 'date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $simulator->create($course_work, $actor, $data['profile'], $data['date'] ?? null)], 201);
    }

    public function show(string $run, TeachingSimulation $simulator): JsonResponse
    {
        return response()->json(['data' => $simulator->public($simulator->read($this->actor()->id, $run))]);
    }

    public function send(string $run, TeachingSimulation $simulator): JsonResponse
    {
        return response()->json(['data' => $simulator->send($this->actor()->id, $run)]);
    }

    public function collect(string $run, TeachingSimulation $simulator): JsonResponse
    {
        return response()->json(['data' => $simulator->collect($this->actor()->id, $run)]);
    }

    public function revise(Request $request, string $run, TeachingSimulation $simulator): JsonResponse
    {
        $data = $request->validate(['participant_id' => ['required', 'string', 'max:100']]);

        return response()->json(['data' => $simulator->revise($this->actor()->id, $run, $data['participant_id'])]);
    }

    public function file(Request $request, string $run, TeachingSimulation $simulator): BinaryFileResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:500']]);
        $path = $simulator->artifact($this->actor()->id, $run, $data['path']);

        return response()->file($path, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function download(string $run, TeachingSimulation $simulator): BinaryFileResponse
    {
        return response()->download($simulator->archive($this->actor()->id, $run), 'Leistungsfeststellungs-Simulation.zip',
            ['Cache-Control' => 'private, no-store']);
    }

    private function actor(): User
    {
        abort_unless(app()->isLocal(), 403);
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $actor->is_active, 403);

        return $actor;
    }
}
