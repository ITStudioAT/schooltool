<?php

namespace App\Http\Controllers;

use App\Models\TeachingExplanationShare;
use App\Services\Teaching\TeachingExplanationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class SharedTeachingExplanationController extends Controller
{
    public function entry(Request $request, string $token): Response
    {
        $share = $this->share($token);
        if ($share->access_mode === 'authenticated' && ! $request->user()?->is_active) {
            return response()->view('teaching-explanation-login', [], 401)->header('Cache-Control', 'private, no-store');
        }

        return response()->view('homepage')->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $share = $this->share($token);
        abort_if($share->access_mode === 'authenticated' && ! $request->user()?->is_active, 401);

        return response()->json(['explanation' => $share->explanation])->header('Cache-Control', 'private, no-store');
    }

    private function share(string $token): TeachingExplanationShare
    {
        abort_unless(Schema::hasTable('teaching_explanation_shares'), 404);

        return TeachingExplanationShare::query()->where('token', $token)->where('is_active', true)
            ->whereIn('access_mode', ['public', 'authenticated'])
            ->whereIn('explanation', array_keys(TeachingExplanationCatalog::all()))->whereHas('user', fn (Builder $query): Builder => $query->where('is_active', true))->firstOrFail();
    }
}
