<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\HolidayResource;
use App\Models\TeachingHoliday;
use App\Services\HolidayService;
use Illuminate\Http\Request;

class MyHolidayController extends Controller
{
    public function index(HolidayService $service)
    {
        if (! $auth_user = $this->userHasRole(['teacher', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $holidays = $service->listVisibleForTeacher($auth_user);

        return response()->json([
            'data' => HolidayResource::collection($holidays),
        ]);
    }

    public function store(Request $request, HolidayService $service)
    {
        if (! $auth_user = $this->userHasRole(['teacher', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_until' => 'nullable|date|after_or_equal:date_from',
            'reason' => 'nullable|string|max:255',
        ]);

        $result = $service->createOwnForUser($auth_user, $validated);

        return response()->json([
            'created' => $result['created'],
            'updated' => $result['updated'],
        ], 201);
    }

    public function destroy(TeachingHoliday $my_holiday, HolidayService $service)
    {
        if (! $auth_user = $this->userHasRole(['teacher', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (
            $my_holiday->school_id !== $auth_user->school_id
            || $my_holiday->schoolyear_id !== $auth_user->schoolyear_id
            || $my_holiday->scope !== 'teacher'
            || (int) $my_holiday->user_id !== (int) $auth_user->id
        ) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $service->deleteOwnForUser($auth_user, $my_holiday);

        return response()->json(null, 204);
    }
}
