<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Requests\Homepage\HomepageLoadSchoolsForToolRequest;
use App\Http\Requests\Homepage\HomepageRoutingRequest;
use App\Http\Requests\Homepage\RestaurantChangePasswordRequest;
use App\Http\Requests\Homepage\RestaurantCheckEmailRequest;
use App\Http\Requests\Homepage\RestaurantConfirmEmailRequest;
use App\Http\Requests\Homepage\RestaurantConfirmUserRequest;
use App\Http\Requests\Homepage\RestaurantLoginWithCodeRequest;
use App\Http\Requests\Homepage\RestaurantLoginWithPasswordRequest;
use App\Http\Requests\Homepage\RestaurantRegisterUserRequest;
use App\Http\Requests\Homepage\RestaurantSendLoginCodeRequest;
use App\Http\Requests\Homepage\RestaurantSepaCompleteRequest;
use App\Http\Requests\Homepage\RestaurantSepaConfirmCodeRequest;
use App\Http\Requests\Homepage\RestaurantSepaStoreRequest;
use App\Http\Resources\Admin\Restaurant\RestaurantMenuPlanResource;
use App\Http\Resources\Homepage\LicenceResource;
use App\Http\Resources\Homepage\SchoolResource;
use App\Http\Resources\Homepage\UserResource;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Services\HomepageRoutingService;
use App\Services\LicenceService;
use App\Services\RestaurantHomepageAuthService;
use App\Services\RestaurantMenuPlanPdfService;
use App\Services\RestaurantSepaMandateService;
use App\Services\RestaurantService;
use App\Services\SchoolToolModuleStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function config(
        Request $request,
        LicenceService $licenceService,
        RestaurantService $restaurantService,
        RestaurantSepaMandateService $restaurantSepaMandateService
    ) {
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
        $restaurantAuthUser = $this->restaurantAuthUser($school);

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
            'auth_check' => $restaurantAuthUser !== null,
            'auth_user' => $restaurantAuthUser ? new UserResource($restaurantAuthUser) : null,
            'health' => [
                'queue_working' => $this->isQueueWorking(),
            ],
            'restaurant' => [
                'user_information_intro_html' => trim((string) ($school?->schoolTool?->restaurant_user_information_intro_html ?? '')),
                'new_users_must_confirm_email' => (bool) ($school?->schoolTool?->restaurant_new_users_must_confirm_email ?? false),
                ...$restaurantService->homepageSummaryForSchool($school),
                ...$restaurantSepaMandateService->publicSettingsForSchool($school),
                'sepa_flow' => $restaurantAuthUser
                    ? $restaurantSepaMandateService->bootstrapFlow($restaurantAuthUser, 'login')
                    : null,
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

    public function restaurantMenuPlanPrint(int $id, RestaurantService $restaurantService, RestaurantMenuPlanPdfService $pdfService): BinaryFileResponse
    {
        $plan = $restaurantService->findVisibleMenuPlan($id);

        if (! $plan) {
            abort(404, 'Menüplan nicht gefunden.');
        }

        $path = $pdfService->createPdf($plan);

        return response()
            ->download($path, basename($path), [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }

    public function restaurantCheckEmail(
        RestaurantCheckEmailRequest $request,
        RestaurantHomepageAuthService $authService
    ) {
        return response()->json(
            $authService->checkEmail($request->validated()['data'])
        );
    }

    public function restaurantRegisterUser(
        RestaurantRegisterUserRequest $request,
        RestaurantHomepageAuthService $authService
    ) {
        return response()->json(
            $authService->register($request->validated()['data'])
        );
    }

    public function restaurantSendLoginCode(
        RestaurantSendLoginCodeRequest $request,
        RestaurantHomepageAuthService $authService
    ) {
        return response()->json(
            $authService->sendLoginCode($request->validated()['data'])
        );
    }

    public function restaurantLoginWithCode(
        RestaurantLoginWithCodeRequest $request,
        RestaurantHomepageAuthService $authService
    ) {
        return response()->json(
            $authService->loginWithCode($request->validated()['data'])
        );
    }

    public function restaurantLoginWithPassword(
        RestaurantLoginWithPasswordRequest $request,
        RestaurantHomepageAuthService $authService
    ) {
        return response()->json(
            $authService->loginWithPassword($request->validated()['data'])
        );
    }

    public function restaurantConfirmEmail(
        RestaurantConfirmEmailRequest $request,
        RestaurantHomepageAuthService $authService
    ) {
        return response()->json(
            $authService->confirmEmail($request->validated()['data'])
        );
    }

    public function restaurantChangePassword(
        RestaurantChangePasswordRequest $request,
        RestaurantHomepageAuthService $authService
    ) {
        return response()->json(
            $authService->changePassword($request->validated()['data'])
        );
    }

    public function restaurantStoreSepaMandate(
        RestaurantSepaStoreRequest $request,
        RestaurantSepaMandateService $restaurantSepaMandateService
    ) {
        return response()->json(
            $restaurantSepaMandateService->submitMandate($request->validated()['data'], (string) $request->ip())
        );
    }

    public function restaurantConfirmSepaMandateCode(
        RestaurantSepaConfirmCodeRequest $request,
        RestaurantSepaMandateService $restaurantSepaMandateService
    ) {
        return response()->json(
            $restaurantSepaMandateService->confirmCode($request->validated()['data'], (string) $request->ip())
        );
    }

    public function restaurantCompleteSepaMandate(
        RestaurantSepaCompleteRequest $request,
        RestaurantSepaMandateService $restaurantSepaMandateService
    ) {
        return response()->json(
            $restaurantSepaMandateService->completeFlow($request->validated()['data'], (string) $request->ip())
        );
    }

    public function restaurantConfirmUser(
        RestaurantConfirmUserRequest $request,
        RestaurantHomepageAuthService $authService,
        LicenceService $licenceService
    ) {
        $validated = $request->validated();
        $user = User::query()->findOrFail((int) $validated['user_id']);

        $this->ensureRestaurantLicenceForSchool($user->selectedSchool, $licenceService);
        $wasConfirmed = $authService->confirmPendingRestaurantUser((int) $validated['user_id'], (string) $validated['token']);

        return response()->view('homepage.restaurant-approval-response', [
            'title' => $wasConfirmed
                ? 'Benutzer wurde erfolgreich bestätigt.'
                : 'Benutzer konnte nicht bestaetigt werden.',
            'subtitle' => $this->restaurantApprovalSubtitle($user),
            'text' => $wasConfirmed
                ? 'Die Freischaltung fuer das Restaurant wurde bearbeitet.'
                : 'Die Anfrage wurde eventuell bereits bearbeitet oder die E-Mail-Adresse ist noch nicht bestaetigt.',
            'status' => $wasConfirmed ? 'BESTAETIGT' : 'NICHT BESTAETIGT',
            'back_url' => url('/homepage/restaurant?school='.$user->selectedSchool?->short_name),
        ]);
    }

    public function restaurantRejectUser(
        RestaurantConfirmUserRequest $request,
        RestaurantHomepageAuthService $authService,
        LicenceService $licenceService
    ) {
        $validated = $request->validated();
        $user = User::query()->findOrFail((int) $validated['user_id']);

        $this->ensureRestaurantLicenceForSchool($user->selectedSchool, $licenceService);

        $wasRejected = $authService->rejectPendingRestaurantUser((int) $validated['user_id'], (string) $validated['token']);

        return response()->view('homepage.restaurant-approval-response', [
            'title' => $wasRejected
                ? 'Benutzer wurde abgelehnt.'
                : 'Benutzer konnte nicht abgelehnt werden.',
            'subtitle' => $this->restaurantApprovalSubtitle($user),
            'text' => $wasRejected
                ? 'Die Restaurantanmeldung wurde abgelehnt.'
                : 'Die Anfrage wurde eventuell bereits bearbeitet oder kann nicht mehr abgelehnt werden.',
            'status' => $wasRejected ? 'ABGELEHNT' : 'NICHT ABGELEHNT',
            'back_url' => url('/homepage/restaurant?school='.$user->selectedSchool?->short_name),
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

    private function ensureRestaurantLicenceForSchool(?School $school, LicenceService $licenceService): void
    {
        $status = $licenceService->licenceStatus($school, 'Restaurant');

        if ($status === 'active') {
            return;
        }

        abort(403, $status === 'expired' ? 'Lizenz abgelaufen.' : 'Lizenz nicht vorhanden.');
    }

    private function restaurantApprovalSubtitle(User $user): string
    {
        $fullName = trim(implode(' ', array_filter([
            trim((string) $user->last_name),
            trim((string) $user->first_name),
        ])));

        if ($fullName === '') {
            return trim((string) $user->email);
        }

        return $fullName.' ('.trim((string) $user->email).')';
    }

    private function restaurantAuthUser(?School $school): ?User
    {
        $authUser = Auth::user();

        if (! $authUser instanceof User) {
            return null;
        }

        if (! $authUser->hasRole('lunch_user')) {
            return null;
        }

        if ($school && (int) $authUser->school_id !== (int) $school->id) {
            return null;
        }

        return $authUser;
    }
}
