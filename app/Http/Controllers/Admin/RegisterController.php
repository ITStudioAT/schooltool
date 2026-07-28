<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterIndexRequest;
use App\Http\Requests\Admin\RegisterStoreRequest;
use App\Http\Requests\Admin\RegisterToggleRequest;
use App\Http\Requests\Admin\RegisterUpdateRequest;
use App\Http\Requests\Admin\SetActiveRegisterRequest;
use App\Http\Resources\Admin\RegisterResource;
use App\Models\Register;
use App\Services\RegisterService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RegisterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(RegisterIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $school_id = $auth_user->school_id;
        $schoolyear_id = $auth_user->schoolyear_id;

        $registers = Register::withCount([
            'bookings',
            'dates',
            'dates as different_dates_count' => function ($q) {
                $q->select(DB::raw('COUNT(DISTINCT date)'));
            },
        ])->where('school_id', $school_id)->where('schoolyear_id', $schoolyear_id)->orderBy('name')->get();

        return response()->json(RegisterResource::collection($registers), 200);
    }

    public function getActiveRegisters()
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $school_id = $auth_user->school_id;

        $registers = Register::select('registers.*')
            ->join('schoolyears', 'schoolyears.id', '=', 'registers.schoolyear_id')
            ->where('registers.school_id', $school_id)
            ->where('registers.is_active', 1)
            ->orderBy('schoolyears.name', 'desc')
            ->orderBy('registers.name')
            ->with(['schoolyear:id,name'])
            ->withCount([
                'bookings',
                'dates',
                'dates as different_dates_count' => function ($q) {
                    $q->select(DB::raw('COUNT(DISTINCT date)'));
                },
            ])
            ->get();

        /*

         'selected_register' =>  $user && $user->selectedRegister ? new RegisterResource($user->selectedRegister->loadCount([
                'bookings',
                'dates',
                'dates as different_dates_count' => function ($q) {
                    $q->select(DB::raw('COUNT(DISTINCT date)'));
                },
            */

        return response()->json(RegisterResource::collection($registers), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RegisterStoreRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();
        $validated['school_id'] = $auth_user->school_id;
        $validated['schoolyear_id'] = $auth_user->schoolyear_id;

        $register = Register::create($validated);

        return response()->json(new RegisterResource($register), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Register $register)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RegisterUpdateRequest $request, Register $register)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Gate::forUser($auth_user)->authorize('update', $register);

        $validated = $request->validated();

        if (
            (int) $validated['id'] !== (int) $register->id
            || (int) $validated['school_id'] !== (int) $register->school_id
            || (int) $validated['schoolyear_id'] !== (int) $register->schoolyear_id
        ) {
            abort(403, 'Die Mandantenzuordnung des Anmeldesystems darf nicht geändert werden.');
        }

        $register->update(Arr::except($validated, ['id', 'school_id', 'schoolyear_id']));

        return response()->json(new RegisterResource($register), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Register $register)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        Gate::forUser($auth_user)->authorize('delete', $register);

        if ($register->hasDependencies()) {
            abort(409, 'Das Anmeldesystem hat noch Abhängigkeiten und kann nicht gelöscht werden');
        }

        $auth_user->register_id = null;
        $auth_user->save();

        $register->delete();

        return response()->noContent();
    }

    public function setActiveRegister(SetActiveRegisterRequest $request, RegisterService $registerService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $register = Register::query()->findOrFail($validated['register_id']);

        Gate::forUser($auth_user)->authorize('view', $register);

        $register = $registerService->setToUser($auth_user, $register->id);
        $register = Register::withCount([
            'bookings',
            'dates',
            'dates as different_dates_count' => function ($q) {
                $q->select(DB::raw('COUNT(DISTINCT date)'));
            },
        ])
            ->findOrFail($register->id);

        return response()->json(new RegisterResource($register), 200);
    }

    public function toggleRegister(RegisterToggleRequest $request, RegisterService $registerService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $register = Register::query()->findOrFail($validated['register_id']);

        Gate::forUser($auth_user)->authorize('update', $register);

        $register = $registerService->toggle($register->id);

        return response()->json(new RegisterResource($register), 200);
    }
}
