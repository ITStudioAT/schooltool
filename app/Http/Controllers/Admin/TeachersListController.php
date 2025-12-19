<?php

namespace App\Http\Controllers\Admin;

use App\Events\TeachersListImportFinishedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TeachersListResource;
use App\Models\School;
use App\Models\Teacher;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class TeachersListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $teachers = Teacher::where('school_id', $auth_user->school_id)->orderBy('short')->orderBy('last_name')->get();
        return response()->json(TeachersListResource::collection($teachers), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function upload(Request $request, FileUploadService $fileUploadService)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $id = $fileUploadService->upload();

        return response($id, 200)->header('Content-Type', 'text/plain');
    }
    public function uploadNext(Request $request, FileUploadService $fileUploadService)
    {

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $result = $fileUploadService->uploadNext(
            $request,
            "app/private/{$auth_user->school_id}/excel",              // final target directory
            "teachers_list"
        );

        // Partial chunk → just forward the 200 "OK" response
        if ($result instanceof Response) {
            return $result; // "OK" or final name wrapped in Response
        }

        \Debugbar::info('app/private/' . $auth_user->school_id . '/excel');
        \Debugbar::info($result);


        broadcast(new TeachersListImportFinishedEvent(
            $auth_user->id,
            'Import erfolgreich abgeschlossen!',
            ['imported' => 150, 'failed' => 2]
        ));



        return response($result, 200)->header('Content-Type', 'text/plain');
    }
}
