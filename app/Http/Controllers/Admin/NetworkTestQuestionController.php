<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkDeviceAnswerKey;
use App\Models\NetworkTestQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class NetworkTestQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->teacher();
        $device = $this->isDeviceSection($request);
        $schoolId = (int) $teacher->school_id;
        if ($device) {
            NetworkDeviceAnswerKey::initializeQuestionsForSchool($schoolId);
        }

        $questions = $this->questions($teacher, $device)->orderBy('id')->get();
        $data = $device ? ['school_id' => $schoolId, 'questions' => $questions] : $questions;

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $teacher = $this->teacher();
        $device = $this->isDeviceSection($request);
        $schoolId = (int) $teacher->school_id;
        $data = $this->validated($request);
        if ($device) {
            $context = $request->validate(['school_id' => ['required', 'integer']]);
            abort_unless((int) $context['school_id'] === $schoolId, 409);
            NetworkDeviceAnswerKey::initializeQuestionsForSchool($schoolId);
        }

        $question = NetworkTestQuestion::query()->create($data + [
            'user_id' => $device ? null : $teacher->id,
            'school_id' => $schoolId,
        ]);

        return response()->json($question, 201);
    }

    public function update(Request $request, int $question): JsonResponse
    {
        $teacher = $this->teacher();
        $device = $this->isDeviceSection($request);
        $entry = DB::transaction(function () use ($request, $teacher, $device, $question): NetworkTestQuestion {
            $entry = $this->editableQuestion($request, $teacher, $device, $question);
            $entry->fill($this->validated($request));
            $entry->revision++;
            $entry->save();

            return $entry;
        }, 3);

        return response()->json($entry);
    }

    public function destroy(Request $request, int $question): Response
    {
        $teacher = $this->teacher();
        $device = $this->isDeviceSection($request);
        DB::transaction(function () use ($request, $teacher, $device, $question): void {
            $this->editableQuestion($request, $teacher, $device, $question)->delete();
        }, 3);

        return response()->noContent();
    }

    private function isDeviceSection(Request $request): bool
    {
        return $request->routeIs('admin.teaching-tools.network-device-questions.*');
    }

    private function teacher(): User
    {
        $teacher = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($teacher && $teacher->is_active && $teacher->school_id, 403);

        return $teacher;
    }

    /** @return Builder<NetworkTestQuestion> */
    private function questions(User $teacher, bool $device): Builder
    {
        return NetworkTestQuestion::query()
            ->where('school_id', $teacher->school_id)
            ->where('user_id', $device ? null : $teacher->id);
    }

    private function editableQuestion(Request $request, User $teacher, bool $device, int $question): NetworkTestQuestion
    {
        $entry = $this->questions($teacher, $device)->lockForUpdate()->findOrFail($question);
        $data = $request->validate(['revision' => [$device ? 'required' : 'sometimes', 'integer', 'min:0', 'max:4294967294']]);
        if (isset($data['revision'])) {
            abort_unless((int) $data['revision'] === $entry->revision, 409);
        }

        return $entry;
    }

    /** @return array{question: string, answer: string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:4000'],
        ]);
    }
}
