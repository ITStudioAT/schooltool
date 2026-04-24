<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\RestaurantSepaSettingsUpdateRequest;
use App\Models\School;
use App\Services\RestaurantSepaMandatePdfService;
use App\Services\RestaurantService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RestaurantSepaSettingsController extends Controller
{
    public function preview(
        RestaurantService $service,
        RestaurantSepaMandatePdfService $pdfService
    ): BinaryFileResponse {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $school = School::query()->findOrFail($authUser->school_id);
        $path = $pdfService->createPreviewPdf($school, $service->sepaSettingsForUser($authUser));

        return response()
            ->file($path, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }

    public function update(
        RestaurantSepaSettingsUpdateRequest $request,
        RestaurantService $service
    ): JsonResponse {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $settings = $service->updateSepaSettings($authUser, $request->validated()['data']);

        return response()->json([
            'data' => $settings,
        ]);
    }
}
