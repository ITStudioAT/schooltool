<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkDeviceAnswerKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkDeviceAnswerKeyController extends Controller
{
    public function show(): JsonResponse
    {
        $schoolId = $this->schoolId();
        $answerKey = NetworkDeviceAnswerKey::forSchool($schoolId);

        return $this->answerKeyResponse($schoolId, $answerKey->answers, $answerKey->revision);
    }

    public function update(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId();
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'revision' => ['required', 'integer', 'min:0', 'max:4294967294'],
            'answers' => ['required', 'array:patch_panel,switch,router,data_server,access_point'],
            'answers.patch_panel' => ['required', 'string', 'max:2000'],
            'answers.switch' => ['required', 'string', 'max:2000'],
            'answers.router' => ['required', 'string', 'max:2000'],
            'answers.data_server' => ['required', 'string', 'max:2000'],
            'answers.access_point' => ['required', 'string', 'max:2000'],
        ]);
        abort_unless((int) $data['school_id'] === $schoolId, 409);

        $revision = (int) $data['revision'];
        $answers = $data['answers'];
        $values = [
            'answers' => json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'revision' => $revision + 1,
            'updated_at' => now(),
        ];
        $answerKeys = NetworkDeviceAnswerKey::query()->toBase();
        $updated = $revision === 0
            ? $answerKeys->insertOrIgnore($values + ['school_id' => $schoolId, 'created_at' => now()])
            : $answerKeys->where('school_id', $schoolId)->where('revision', $revision)->update($values);
        abort_unless($updated === 1, 409);

        return $this->answerKeyResponse($schoolId, $answers, $revision + 1);
    }

    private function schoolId(): int
    {
        $teacher = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($teacher && $teacher->is_active && $teacher->school_id, 403);

        return (int) $teacher->school_id;
    }

    private function answerKeyResponse(int $schoolId, array $answers, int $revision): JsonResponse
    {
        return response()->json(['school_id' => $schoolId, 'answers' => $answers, 'revision' => $revision])
            ->header('Cache-Control', 'private, no-store');
    }
}
