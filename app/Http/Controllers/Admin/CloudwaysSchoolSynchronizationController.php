<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SynchronizeCloudwaysSchoolRequest;
use App\Models\School;
use App\Models\User;
use App\Services\CloudwaysSchoolSynchronizationService;
use Illuminate\Http\JsonResponse;

class CloudwaysSchoolSynchronizationController extends Controller
{
    public function preview(
        School $school,
        CloudwaysSchoolSynchronizationService $service,
    ): JsonResponse {
        return response()->json(['data' => $service->preview($school)]);
    }

    public function store(
        SynchronizeCloudwaysSchoolRequest $request,
        School $school,
        CloudwaysSchoolSynchronizationService $service,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'message' => 'Die Schule wurde vollständig aus Cloudways synchronisiert.',
            'data' => $service->synchronize($school, $actor),
        ]);
    }
}
