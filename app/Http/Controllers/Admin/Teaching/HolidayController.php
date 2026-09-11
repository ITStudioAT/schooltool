<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\HolidayResource;
use App\Models\TeachingHoliday;
use App\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HolidayController extends Controller
{
    public function exportFile(HolidayService $service): StreamedResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $json = json_encode($service->exportForUser($authUser), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return response()->streamDownload(function () use ($json): void {
            echo $json;
        }, "ferien-{$authUser->schoolyear_id}.json", ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function importFile(Request $request, HolidayService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $request->validate([
            'file' => ['required', 'file', 'extensions:json', 'mimetypes:application/json,text/plain', 'max:2048'],
        ], [
            'file.required' => 'Bitte wählen Sie eine Ferien-Exportdatei aus.',
            'file.file' => 'Die Datei konnte nicht hochgeladen werden.',
            'file.uploaded' => 'Die Datei konnte nicht hochgeladen werden.',
            'file.extensions' => 'Bitte wählen Sie eine JSON-Datei aus.',
            'file.mimetypes' => 'Bitte wählen Sie eine JSON-Datei aus.',
            'file.max' => 'Die Ferien-Datei darf höchstens 2 MB groß sein.',
        ]);

        return response()->json($service->importForUser($authUser, $request->file('file')->getContent()));
    }

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
