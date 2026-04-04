<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $notes = Note::where('user_id', $user->id)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_pinned' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        $note = Note::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'content' => $request->content,
            'is_pinned' => $request->boolean('is_pinned', false),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully',
            'data' => $note,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note): JsonResponse
    {
        $user = Auth::user();

        // Authorization check - user can only view their own notes
        if ($note->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $note,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note): JsonResponse
    {
        $user = Auth::user();

        // Authorization check - user can only update their own notes
        if ($note->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_pinned' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $note->update($request->only(['title', 'content', 'is_pinned']));

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'data' => $note->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note): JsonResponse
    {
        $user = Auth::user();

        // Authorization check - user can only delete their own notes
        if ($note->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully',
        ]);
    }

    /**
     * Toggle pin status of a note.
     */
    public function togglePin(Note $note): JsonResponse
    {
        $user = Auth::user();

        // Authorization check - user can only toggle their own notes
        if ($note->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $note->update([
            'is_pinned' => ! $note->is_pinned,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note pin status updated',
            'data' => $note->fresh(),
        ]);
    }
}
