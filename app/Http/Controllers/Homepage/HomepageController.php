<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Requests\Homepage\HomepageLoadSchoolsForToolRequest;
use App\Http\Requests\Homepage\HomepageRoutingRequest;
use App\Http\Resources\Homepage\LicenceResource;
use App\Http\Resources\Homepage\SchoolResource;
use App\Models\Licence;
use App\Models\School;
use App\Services\HomepageRoutingService;
use App\Services\LicenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class HomepageController extends Controller
{
    public function index(Request $request) {}
    public function routing(HomepageRoutingRequest $request, HomepageRoutingService $service)
    {

        $validated = $request->validated();

        $answer = $service->checkRoute($validated['school'] ?? null, $validated['licence'] ?? null);

        if ($answer['status'] == 'error') return redirect('/homepage/error?msg=' . $answer['msg']);

        $redirectUrl = $answer['redirect'];

        $parts = parse_url($redirectUrl);
        parse_str($parts['query'] ?? '', $query);

        $licence = $query['licence'] ?? null;
        $school = $query['school'] ?? null;

        switch ($licence) {
            case 'Anmeldetool':
                return redirect('/homepage/register?school=' . $school);
                break;

            default:
                // fallback if none match
                break;
        }

        return redirect($answer['redirect']);
    }

    public function loadSchoolsForTool(HomepageLoadSchoolsForToolRequest $request)
    {
        $validated = $request->validated();
        $licence = Licence::where('name', $validated['tool'])->first();

        $data = [
            'licence' => new LicenceResource($licence),
            'schools' => SchoolResource::collection($licence->schools->sortBy('long_name')->values())
        ];

        return response()->json($data, 200);
    }



    public function config(Request $request, LicenceService $licenceService)
    {
        $school_short = $request->query('school');
        $app = $request->query('app');

        // Prüfen, ob Schule existiert
        $isSchoolValid = ($school = School::where('short_name', $school_short)->first()) != null;

        // Prüfen, ob App-Lizenz existiert bzw. gültig ist
        $isLicenceValid = $licenceService->isLicenceValid($school, $app);

        // Laden aller auswählbaren Schulen
        $schools = School::selectables()->get();


        // Wenn es eine Schule gibt, die gültige Lizenzen holen
        if ($isSchoolValid) {
            $schoolLicences = $school->selectableValidLicences()->get();
        } else {
            if (count($schools) == 1) {
                $school = $schools->first();
                $isSchoolValid = true;
                $schoolLicences = $school->selectableValidLicences()->get();
                $isLicenceValid = $licenceService->isLicenceValid($school, $app);
            } else {
                $schoolLicences = [];
            }
        }

        if ($isLicenceValid) {
            $licence = Licence::where('name', $app)->first();
        } else {
            if (count($schoolLicences) == 1) {
                $licence = $schoolLicences->first();
                $isLicenceValid = true;
            } else {
                $licence = null;
            }
        }



        $data = [
            'schooltool_logo' => config('schooltool.logo'),
            'logo' => $school ? $school->logo : null,
            'version' => config('schooltool.version', 'x.x.x'),
            'copyright' => config('schooltool.copyright', ''),
            'title' => 'SchoolTool',
            'isSchoolValid' => $isSchoolValid,
            'school' => $isSchoolValid ? new SchoolResource($school) : null,
            'isLicenceValid' =>  $isLicenceValid,
            'licence' => $isLicenceValid ? new LicenceResource($licence) : null,
            'selectableSchools' => SchoolResource::collection($schools),
            'schoolLicences' => $school ? LicenceResource::collection($schoolLicences) : []
        ];

        return response()->json($data, 200);
    }

    public function logout()
    {
        if (Auth::check()) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();
        }
    }
}
