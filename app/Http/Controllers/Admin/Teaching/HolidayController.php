<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\HolidayResource;
use App\Models\TeachingHoliday;
use App\Services\HolidayService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(HolidayService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $holidays = $service->listForUser($auth_user);

        return response()->json([
            'data' => HolidayResource::collection($holidays),
        ]);
    }

    public function store(Request $request, HolidayService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_until' => 'nullable|date|after_or_equal:date_from',
            'reason' => 'nullable|string|max:255',
        ]);

        $result = $service->createForUser($auth_user, $validated);

        return response()->json([
            'created' => $result['created'],
            'updated' => $result['updated'],
        ], 201);
    }

    public function destroy(TeachingHoliday $holiday, HolidayService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (
            $holiday->school_id !== $auth_user->school_id
            || $holiday->schoolyear_id !== $auth_user->schoolyear_id
            || $holiday->scope !== 'school'
        ) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $service->deleteForUser($auth_user, $holiday);

        return response()->json(null, 204);
    }
}
