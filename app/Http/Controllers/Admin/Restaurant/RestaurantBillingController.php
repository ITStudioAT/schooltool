<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Restaurant\StoreRestaurantBillingRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantBillingResource;
use App\Services\RestaurantBillingPdfService;
use App\Services\RestaurantBillingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RestaurantBillingController extends Controller
{
    public function index(RestaurantBillingService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => RestaurantBillingResource::collection($service->billingsForUser($authUser)),
            'weeks' => $service->weeksForUser($authUser),
        ]);
    }

    public function store(StoreRestaurantBillingRequest $request, RestaurantBillingService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $billing = $service->createForUser($authUser, $request->validated());

        return response()->json([
            'message' => 'Abrechnung wurde erstellt.',
            'data' => RestaurantBillingResource::make($billing),
        ], 201);
    }

    public function print(int $id, RestaurantBillingService $service, RestaurantBillingPdfService $pdfService): BinaryFileResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'lunch_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $billing = $service->findForUser($authUser, $id);

        if (! $billing) {
            abort(404, 'Abrechnung nicht gefunden.');
        }

        $path = $pdfService->createPdf($billing);

        return response()
            ->download($path, basename($path), [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }
}
