<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Services\StudentEmailZipImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentEmailZipImportController extends Controller
{
    public function __invoke(Request $request, StudentEmailZipImportService $importer): JsonResponse
    {
        if (! $user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (! $user->schoolyear_id) {
            abort(422, 'Kein persönliches Schuljahr ausgewählt.');
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:10240'],
        ]);

        return response()->json($importer->import(
            $validated['file']->getRealPath(),
            (int) $user->school_id,
            (int) $user->schoolyear_id,
        ));
    }
}
