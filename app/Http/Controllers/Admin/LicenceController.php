<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LicenceDeleteLicencesRequest;
use App\Http\Requests\Admin\LicenceIndexRequest;
use App\Http\Requests\Admin\LicenceSaveModelRequest;
use App\Http\Requests\Admin\LicenceStoreRequest;
use App\Http\Requests\Admin\LicenceUpdateRequest;
use App\Http\Resources\Admin\LicenceResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\Licence;
use App\Models\SchoolLicence;
use App\Services\LicenceService;

class LicenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(LicenceIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;

        $licences = Licence::query()
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('long_name', 'like', "%{$search_string}%")
                        ->orWhere('name', 'like', "%{$search_string}%");
                });
            })
            ->orderBy('long_name')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => LicenceResource::collection($licences),
            'meta' => new PaginateResource($licences),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LicenceStoreRequest $request, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $licence = Licence::create($validated);

        return response()->json(new LicenceResource($licence), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Licence $licence)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LicenceUpdateRequest $request, Licence $licence)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $licence->update($validated);

        return response()->json(new LicenceResource($licence), 200);
    }

    public function saveLicenceModel(LicenceSaveModelRequest $request, Licence $licence, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $licence = $service->saveLicenceModel($licence, $validated['licence_model']);

        return response()->json(new LicenceResource($licence), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Licence $licence)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (SchoolLicence::where('licence_id', $licence->id)->exists()) {
            abort(409, 'Die Lizenz ist noch einer oder mehreren Schulen zugeordnet und kann nicht gelöscht werden.');
        }

        $licence->delete();

        return response()->noContent();
    }

    public function loadLicences()
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $licences = Licence::orderBy('long_name')->get();

        return response()->json(LicenceResource::collection($licences), 200);
    }

    public function deleteLicences(LicenceDeleteLicencesRequest $request, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $service->deleteLicences($validated);

        return response()->noContent();
    }
}
