<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkTestQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NetworkTestQuestionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json($this->questions($this->teacher())->orderBy('id')->get())
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $teacher = $this->teacher();
        $question = NetworkTestQuestion::query()->create($this->validated($request) + [
            'user_id' => $teacher->id,
            'school_id' => $teacher->school_id,
        ]);

        return response()->json($question, 201);
    }

    public function update(Request $request, int $question): JsonResponse
    {
        $entry = $this->questions($this->teacher())->findOrFail($question);
        $entry->update($this->validated($request));

        return response()->json($entry);
    }

    public function destroy(int $question): Response
    {
        abort_unless($this->questions($this->teacher())->whereKey($question)->delete() === 1, 404);

        return response()->noContent();
    }

    private function teacher(): User
    {
        $teacher = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($teacher && $teacher->is_active && $teacher->school_id, 403);

        return $teacher;
    }

    /** @return Builder<NetworkTestQuestion> */
    private function questions(User $teacher): Builder
    {
        return NetworkTestQuestion::query()->where('user_id', $teacher->id)->where('school_id', $teacher->school_id);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:4000'],
        ]);
    }
}
