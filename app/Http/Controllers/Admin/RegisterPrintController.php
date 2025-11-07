<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterPrintExcelRequest;
use App\Jobs\PrintExcelJob;
use App\Services\PrintService;
use Illuminate\Http\Request;

class RegisterPrintController extends Controller
{
    public function printExcel(RegisterPrintExcelRequest $request, PrintService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        // Job dispatch
        PrintExcelJob::dispatch($auth_user, $validated);


        return response()->noContent();
    }
}
