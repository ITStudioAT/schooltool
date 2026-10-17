<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NumberSystems\NumberSystemGameResults;
use App\Services\NumberSystems\NumberSystemLocalProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NumberSystemLocalProbeController extends Controller
{
    public function __construct(private NumberSystemLocalProbe $probes) {}

    public function create(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $data = $request->validate(['planned_minutes' => ['required', 'integer', Rule::in([30, 40, 60, 75])],
            'difficulty' => ['required', 'integer', 'between:1,3'], 'mode' => ['required', Rule::in(['solo', 'teams'])], 'short' => ['required', 'boolean'], ...NumberSystemGameResults::rules()]);

        return $this->respond($this->probes->create($owner, $data), 201);
    }

    public function show(Request $request, string $probe): JsonResponse
    {
        return $this->respond($this->probes->show($this->owner($request), $probe));
    }

    public function control(Request $request, string $probe): JsonResponse
    {
        $owner = $this->owner($request);
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'pause', 'resume', 'end'])]]);

        return $this->respond($this->probes->control($owner, $probe, $data['action']));
    }

    public function player(Request $request, string $probe, int $participant): JsonResponse
    {
        return $this->respond($this->probes->player($this->owner($request), $probe, $participant));
    }

    public function join(Request $request, string $probe, int $participant): JsonResponse
    {
        $owner = $this->owner($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:20'], 'team_name' => ['nullable', 'string', 'max:60'], 'team_code' => ['nullable', 'string', 'max:12']]);

        return $this->respond(['run' => $this->probes->join($owner, $probe, $participant, $data)]);
    }

    public function update(Request $request, string $probe, int $participant): JsonResponse
    {
        $owner = $this->owner($request);
        $data = $request->validate(['action' => ['required', Rule::in(['draft', 'answer', 'hint', 'advance'])],
            'revision' => ['required', 'integer', 'min:0'], 'mission_id' => ['required', 'string', 'size:24'], 'draft' => ['sometimes', 'array', 'max:15']]);
        if (in_array($data['action'], ['draft', 'answer'], true)) {
            abort_unless(array_key_exists('draft', $data), 422);
        }

        return $this->respond(['run' => $this->probes->update($owner, $probe, $participant, $data)]);
    }

    public function presence(Request $request, string $probe, int $participant): JsonResponse
    {
        return $this->respond($this->probes->presence($this->owner($request), $probe, $participant));
    }

    public function remove(Request $request, string $probe): JsonResponse
    {
        $this->probes->remove($this->owner($request), $probe);

        return $this->respond(['removed' => true]);
    }

    private function owner(Request $request): int
    {
        abort_unless($this->probes->available($request), 404);
        $teacher = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($teacher && $teacher->school_id, 403);

        return (int) $teacher->id;
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
