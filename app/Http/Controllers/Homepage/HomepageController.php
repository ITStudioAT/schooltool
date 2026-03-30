<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Requests\Homepage\HomepageLoadSchoolsForToolRequest;
use App\Http\Requests\Homepage\HomepageRoutingRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantMenuPlanResource;
use App\Http\Resources\Homepage\LicenceResource;
use App\Http\Resources\Homepage\SchoolResource;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Services\HomepageRoutingService;
use App\Services\LicenceService;
use App\Services\RestaurantService;
use App\Services\SchoolToolModuleStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class HomepageController extends Controller
{
    public function index(Request $request) {}

    public function routing(HomepageRoutingRequest $request, HomepageRoutingService $service)
    {
        $validated = $request->validated();

        // SPA entry on hard refresh (e.g. /homepage/student) without route params.
        // Avoid redirect loops by directly returning the homepage shell.
        if (empty($validated['school']) && empty($validated['licence'])) {
            return view('homepage');
        }

        $answer = $service->checkRoute($validated['school'] ?? null, $validated['licence'] ?? null);

        if ($answer['status'] == 'error') {
            return redirect('/homepage/error?msg='.$answer['msg']);
        }

        $redirectUrl = $answer['redirect'];

        $parts = parse_url($redirectUrl);
        parse_str($parts['query'] ?? '', $query);

        $licence = $query['licence'] ?? null;
        $school = $query['school'] ?? null;

        switch ($licence) {
            case 'Anmeldetool':
                return redirect('/homepage/register?school='.$school);
            case 'Nachhilfetool':
                return redirect('/homepage/tutoring_overview?school='.$school);
            case 'Lehrertool':
                return redirect('/homepage/student?school='.$school);

            default:
                // Fallback to SPA shell to avoid redirect loops on unknown/empty targets.
                return view('homepage');
        }
    }

    public function loadSchoolsForTool(HomepageLoadSchoolsForToolRequest $request)
    {
        $validated = $request->validated();
        $licenceService = app(LicenceService::class);
        $toolData = $licenceService->selectableSchoolsForTool($validated['tool']);
        $licence = $toolData['licence'];

        if (! $licence) {
            return response()->json([
                'licence' => null,
                'schools' => [],
                'status' => 'missing',
            ], 200);
        }

        $data = [
            'licence' => new LicenceResource($licence),
            'schools' => SchoolResource::collection($toolData['schools']),
            'status' => $toolData['status'],
        ];

        return response()->json($data, 200);
    }

    public function config(Request $request, LicenceService $licenceService, RestaurantService $restaurantService)
    {
        $school_short = $request->query('school');
        $app = $request->query('app');
        $moduleStatusService = app(SchoolToolModuleStatusService::class);

        // Prüfen, ob Schule existiert
        $isSchoolValid = ($school = School::where('short_name', $school_short)->first()) != null;

        // Prüfen, ob App-Lizenz existiert bzw. gültig ist
        $isLicenceValid = $licenceService->isLicenceValid($school, $app);

        // Laden aller auswählbaren Schulen
        $schools = School::selectables()->get();

        // Wenn es eine Schule gibt, die gültige Lizenzen holen
        if ($isSchoolValid) {
            $schoolLicences = $licenceService->selectableActiveLicencesForSchool($school);
        } else {
            if (count($schools) == 1) {
                $school = $schools->first();
                $isSchoolValid = true;
                $schoolLicences = $licenceService->selectableActiveLicencesForSchool($school);
                $isLicenceValid = $licenceService->isLicenceValid($school, $app);
            } else {
                $schoolLicences = collect();
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

        $registerModuleStatus = $moduleStatusService->userStatusForModule('register', $school);
        $tutoringModuleStatus = $moduleStatusService->userStatusForModule('tutoring', $school);
        $teachingModuleStatus = $moduleStatusService->userStatusForModule('teaching', $school);
        $materialsModuleStatus = $moduleStatusService->userStatusForModule('materials', $school);
        $restaurantModuleStatus = $moduleStatusService->userStatusForModule('restaurant', $school);

        $data = [
            'schooltool_logo' => config('schooltool.logo'),
            'logo' => $school ? $school->logo : null,
            'version' => config('schooltool.version', 'x.x.x'),
            'copyright' => config('schooltool.copyright', ''),
            'title' => 'SchoolTool',
            'isSchoolValid' => $isSchoolValid,
            'school' => $isSchoolValid ? new SchoolResource($school) : null,
            'isLicenceValid' => $isLicenceValid,
            'licence' => $isLicenceValid ? new LicenceResource($licence) : null,
            'selectableSchools' => SchoolResource::collection($schools),
            'schoolLicences' => $school ? LicenceResource::collection($schoolLicences) : [],
            'register_active' => $moduleStatusService->allowsUserAccess($registerModuleStatus),
            'tutoring_active' => $moduleStatusService->allowsUserAccess($tutoringModuleStatus),
            'teaching_active' => $moduleStatusService->allowsUserAccess($teachingModuleStatus),
            'materials_active' => $moduleStatusService->allowsUserAccess($materialsModuleStatus),
            'restaurant_active' => $moduleStatusService->allowsUserAccess($restaurantModuleStatus),
            'tool_module_statuses' => [
                'register' => $registerModuleStatus,
                'tutoring' => $tutoringModuleStatus,
                'teaching' => $teachingModuleStatus,
                'materials' => $materialsModuleStatus,
                'restaurant' => $restaurantModuleStatus,
            ],
            'tool_licence_statuses' => [
                'Anmeldetool' => $this->toolLicenceStatus('Anmeldetool', $licenceService),
                'Nachhilfetool' => $this->toolLicenceStatus('Nachhilfetool', $licenceService),
                'Lehrertool' => $this->toolLicenceStatus('Lehrertool', $licenceService),
            ],
            'health' => [
                'queue_working' => $this->isQueueWorking(),
            ],
            'restaurant' => [
                'user_information_intro_html' => trim((string) ($school?->schoolTool?->restaurant_user_information_intro_html ?? '')),
                ...$restaurantService->homepageSummaryForSchool($school),
            ],
        ];

        return response()->json($data, 200);
    }

    private function toolLicenceStatus(string $toolName, LicenceService $licenceService): string
    {
        return $licenceService->selectableSchoolLicenceOverview($toolName)['overall_status'];
    }

    private function isQueueWorking(): bool
    {
        $lastHealthAt = SchoolTool::query()->whereNotNull('health_at')->max('health_at');
        if (! $lastHealthAt) {
            return true;
        }

        return Carbon::parse($lastHealthAt)->greaterThan(now()->subMinutes(2));
    }

    public function restaurantMenuPlans(Request $request, RestaurantService $restaurantService)
    {
        $schoolShort = $request->query('school');
        $school = $schoolShort ? School::where('short_name', $schoolShort)->first() : null;

        $plans = $restaurantService->visibleMenuPlansForSchool($school);

        return response()->json([
            'plans' => RestaurantMenuPlanResource::collection($plans),
        ]);
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
