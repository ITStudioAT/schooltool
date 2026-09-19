<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFeaturePreviewSettingsRequest;
use App\Http\Requests\Admin\UpdateFeaturePreviewUserRequest;
use App\Models\FeaturePreviewSetting;
use App\Models\User;
use App\Services\FeaturePreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FeaturePreviewController extends Controller
{
    public function index(Request $request, FeaturePreviewService $preview): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->is_active && $user->hasRole('super_admin'), 403);

        return response()->json(['data' => $preview->managementState($user)]);
    }

    public function updateSettings(UpdateFeaturePreviewSettingsRequest $request, FeaturePreviewService $preview): JsonResponse
    {
        abort_if($preview->isPreview(), 403, 'Die Vorschau wird ausschließlich in der Hauptanwendung verwaltet.');
        abort_unless($preview->schemaReady(), 503, 'Die Vorschauverwaltung ist noch nicht eingerichtet.');
        $enabled = (bool) $request->validated('enabled');
        $settings = FeaturePreviewSetting::query()->find(1) ?? new FeaturePreviewSetting;
        $settings->id = 1;
        $settings->enabled = $enabled;
        $settings->save();
        Log::info('Feature preview availability changed', ['actor_id' => $request->user()->id, 'enabled' => $enabled]);

        return response()->json(['data' => $preview->managementState($request->user())]);
    }

    public function updateUser(UpdateFeaturePreviewUserRequest $request, User $user, FeaturePreviewService $preview): JsonResponse
    {
        abort_if($preview->isPreview(), 403, 'Die Vorschau wird ausschließlich in der Hauptanwendung verwaltet.');
        abort_unless((int) $user->school_id === (int) $request->user()->school_id, 404);
        abort_unless($preview->schemaReady(), 503, 'Die Vorschauverwaltung ist noch nicht eingerichtet.');
        $allowed = (bool) $request->validated('allowed');

        if ($allowed && ! $preview->eligible($user)) {
            throw ValidationException::withMessages(['allowed' => ['Nur aktive, bestätigte Konten können freigegeben werden. Bei Benutzerkonten ohne Admin-Zugang genügt eine verifizierte E-Mail-Adresse.']]);
        }

        $user->feature_preview_allowed = $allowed;
        $user->save();
        Log::info('Feature preview access changed', ['actor_id' => $request->user()->id, 'user_id' => $user->id, 'allowed' => $allowed]);

        return response()->json(['data' => $preview->managementState($request->user())]);
    }
}
