<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterPrintRequest;
use App\Jobs\PrintRegisterDateJob;
use App\Jobs\PrintRegisterExcelJob;
use App\Jobs\PrintRegisterSupervisorJob;
use Illuminate\Http\Request;

class RegisterPrintController extends Controller
{
    public function printExcel(RegisterPrintRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        // Job dispatch
        PrintRegisterExcelJob::dispatch($auth_user, $validated);


        return response()->noContent();
    }

    public function printSupervisor(RegisterPrintRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        // Job dispatch
        PrintRegisterSupervisorJob::dispatch($auth_user, $validated);


        return response()->noContent();
    }

    public function printDate(RegisterPrintRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        // Job dispatch
        PrintRegisterDateJob::dispatch($auth_user, $validated);


        return response()->noContent();
    }
}
