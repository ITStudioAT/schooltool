<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AdminNewTeacherStepCodeRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepEmailRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepSchoolRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepPasswordRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepSchoolRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepToken2Request;
use App\Http\Requests\Admin\AdminPasswordUnknownStepTokenRequest;
use App\Http\Requests\Admin\LoginStep2Request;
use App\Http\Requests\Admin\LoginStep3Request;
use App\Http\Requests\Admin\LoginStepEmailRequest;
use App\Http\Requests\Admin\PasswordUnknownStepEmailRequest;
use App\Http\Requests\Admin\RegisterStep1Request;
use App\Http\Requests\Admin\RegisterStep2Request;
use App\Http\Requests\Admin\RegisterStep3Request;
use App\Http\Resources\Admin\RegisterResource;
use App\Http\Resources\Admin\RoleResource;
use App\Http\Resources\Admin\SchoolResource;
use App\Http\Resources\Admin\SchoolyearResource;
use App\Http\Resources\Admin\UserWithRoleResource;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AdminNavigationService;
use App\Services\AdminService;
use App\Services\SchoolService;
use App\Services\SchoolyearService;
use App\Services\TeacherListService;
use App\Traits\HasRoleTrait;
use Composer\InstalledVersions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Services\ImpersonateManager;

class AdminController extends Controller
{
    use HasRoleTrait;

    private const string ENVIRONMENT_VERSIONS_CACHE_KEY = 'admin.environment_versions.v13';

    /** @var array<string, string|null>|null */
    private ?array $runtimeVersionSnapshot = null;

    private const array STUDENTS_TIMETABLES_ROLES = [
        'super_admin',
        'admin',
        'studentstimetables_admin',
        'studentstimetables_moderator',
    ];

    public function config(Request $request)
    {
        // Laden aller auswählbaren Schulen
        // $schools = School::selectables()->get();

        // $viaRemember = Auth::viaRemember();

        $data = $this->getConfigData(
            $request->boolean('include_school_infos'),
            $request->boolean('include_environment_versions'),
        );

        return response()->json($data, 200);
    }

    private function getConfigData(bool $includeSchoolInfos = false, bool $includeEnvironmentVersions = false)
    {
        $navigationService = new AdminNavigationService;

        /** @var User|null $user */
        $user = Auth::check() ? Auth::user() : null;
        if ($user && ! $user->schoolyear_id && $user->hasAnyRole(self::STUDENTS_TIMETABLES_ROLES)) {
            app(SchoolyearService::class)->ensureActualSchoolyearForUser($user);
        }

        $user?->loadMissing([
            'roles',
            'selectedSchool.schoolTool',
            'selectedSchoolyear',
            'selectedRegister',
        ]);

        /** @var ImpersonateManager $impersonateManager */
        $impersonateManager = app(ImpersonateManager::class);
        $isImpersonating = $user ? $impersonateManager->isImpersonating() : false;
        $impersonator = null;
        if ($isImpersonating) {
            $impersonatorId = $impersonateManager->getImpersonatorId();
            if ($impersonatorId) {
                $impersonator = User::query()
                    ->with('selectedSchool:id,long_name,short_name')
                    ->find($impersonatorId);
            }
        }

        $lastImport116At = null;
        if ($user) {
            $lastImport116At = $user->selectedSchool?->schoolTool?->import_166_at;
        }
        $schoolwideActiveSchoolyear = $this->schoolwideActiveSchoolyear($user);

        $menu = $user ? $navigationService->dashboardMenu() : [];
        $capabilities = $navigationService->routeCapabilities($user, $menu);

        $data = [
            'logo' => config('schooltool.logo', ''),
            'copyright' => config('schooltool.copyright', ''),
            'title' => 'SchoolTool',
            'company' => 'ITStudio Dipl.-Ing. Günther Kron',
            'version' => config('schooltool.version', 'x.x.x'),
            'timeout' => config('spa.timeout', 3000),
            'payment_active' => config('schooltool.payment_active', false),
            'licence_renewal_days' => (int) config('schooltool.licence_renewal_days', 30),
            'is_auth' => Auth::check(),
            'user' => $user ? new UserWithRoleResource($user) : null,
            'selected_school' => $user && $user->selectedSchool ? new SchoolResource($user->selectedSchool) : null,
            'selected_schoolyear' => $user && $user->selectedSchoolyear ? new SchoolyearResource($user->selectedSchoolyear) : null,
            'schoolwide_active_schoolyear' => $schoolwideActiveSchoolyear ? new SchoolyearResource($schoolwideActiveSchoolyear) : null,
            'selected_register' => $user && $user->selectedRegister ? new RegisterResource($user->selectedRegister->loadCount([
                'bookings',
                'dates',
                'dates as different_dates_count' => function ($q) {
                    $q->select(DB::raw('COUNT(DISTINCT date)'));
                },
            ])) : null,
            'menu' => $menu,
            'capabilities' => $capabilities,
            'roles' => $user ? $user->getRoleNames() : [],
            'impersonation' => [
                'is_impersonating' => $isImpersonating,
                'impersonator' => $impersonator ? [
                    'id' => $impersonator->id,
                    'last_name' => $impersonator->last_name,
                    'first_name' => $impersonator->first_name,
                    'email' => $impersonator->email,
                    'school_id' => $impersonator->school_id,
                    'school_name' => trim((string) ($impersonator->selectedSchool?->long_name ?: $impersonator->selectedSchool?->short_name)),
                ] : null,
            ],
            'teaching' => [
                'last_import_116_at' => $lastImport116At,
            ],
            'students_timetables' => [
                'admin_version' => SchoolTool::normalizeStudentsTimetablesAdminVersion(
                    $user?->selectedSchool?->schoolTool?->students_timetables_admin_version,
                ),
            ],
        ];

        $data['health']['queue_working'] = true;

        if ($user && $includeEnvironmentVersions && $this->canLoadEnvironmentVersions($user)) {
            $data['environment_versions'] = $this->environmentVersions();
        }

        if ($includeSchoolInfos && $user?->selectedSchool && $this->canLoadSchoolInfos($user)) {
            $data['school_infos'] = app(SchoolService::class)->schoolInfos($user->selectedSchool);
        }

        return $data;
    }

    private function schoolwideActiveSchoolyear(?User $user): ?Schoolyear
    {
        $schoolId = $user?->selectedSchool?->id;
        $schoolyearId = $user?->selectedSchool?->schoolTool?->active_schoolyear_id;

        if (! $schoolId || ! $schoolyearId) {
            return null;
        }

        return Schoolyear::query()
            ->where('school_id', $schoolId)
            ->find($schoolyearId);
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentVersions(): array
    {
        $cachedVersions = Cache::get(self::ENVIRONMENT_VERSIONS_CACHE_KEY);
        if (is_array($cachedVersions)) {
            return $cachedVersions;
        }

        $versions = [
            'app' => config('schooltool.version', 'x.x.x'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'composer' => $this->configuredRuntimeVersion('composer'),
            'npm' => $this->configuredRuntimeVersion('npm'),
            'node' => $this->configuredRuntimeVersion('node'),
            'vue' => $this->packageLockVersion('vue'),
            'vuetify' => $this->packageLockVersion('vuetify'),
            'vite' => $this->packageLockVersion('vite'),
            'about' => [
                'packages' => [
                    'pulse' => $this->composerPackageVersion('laravel/pulse'),
                    'livewire' => $this->composerPackageVersion('livewire/livewire'),
                    'permissions' => $this->composerPackageVersion('spatie/laravel-permission'),
                ],
                'environment' => [
                    'environment' => app()->environment(),
                    'debug_mode' => (bool) config('app.debug'),
                    'url' => $this->applicationHost(),
                    'maintenance_mode' => app()->isDownForMaintenance(),
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale'),
                ],
                'cache' => [
                    'config' => app()->configurationIsCached(),
                    'events' => app()->eventsAreCached(),
                    'routes' => app()->routesAreCached(),
                    'views' => $this->viewsAreCached(),
                ],
                'drivers' => [
                    'broadcasting' => config('broadcasting.default'),
                    'cache' => config('cache.default'),
                    'database' => config('database.default'),
                    'logs' => config('logging.default'),
                    'mail' => config('mail.default'),
                    'queue' => config('queue.default'),
                    'scout' => config('scout.driver'),
                    'session' => config('session.driver'),
                ],
            ],
        ];

        Cache::put(self::ENVIRONMENT_VERSIONS_CACHE_KEY, $versions, now()->addMinutes(10));

        return $versions;
    }

    private function configuredRuntimeVersion(string $configKey): ?string
    {
        $configuredVersion = config("schooltool.environment_versions.{$configKey}");
        if (is_string($configuredVersion) && filled($configuredVersion)) {
            return trim($configuredVersion);
        }

        return $this->runtimeVersionSnapshot()[$configKey] ?? null;
    }

    /** @return array<string, string|null> */
    private function runtimeVersionSnapshot(): array
    {
        if ($this->runtimeVersionSnapshot !== null) {
            return $this->runtimeVersionSnapshot;
        }

        $path = storage_path('framework/environment-versions.json');
        if (! is_file($path)) {
            return $this->runtimeVersionSnapshot = [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return $this->runtimeVersionSnapshot = [];
        }

        $versions = json_decode($contents, true);
        if (! is_array($versions)) {
            return $this->runtimeVersionSnapshot = [];
        }

        return $this->runtimeVersionSnapshot = collect(['composer', 'npm', 'node'])
            ->mapWithKeys(function (string $key) use ($versions): array {
                $version = $versions[$key] ?? null;

                return [$key => is_string($version) && filled($version) ? trim($version) : null];
            })
            ->all();
    }

    private function packageLockVersion(string $package): ?string
    {
        $path = base_path('package-lock.json');
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $lock = json_decode($contents, true);
        if (! is_array($lock)) {
            return null;
        }

        $packageData = $lock['packages']["node_modules/{$package}"] ?? null;

        return is_array($packageData) && isset($packageData['version'])
            ? (string) $packageData['version']
            : null;
    }

    private function composerPackageVersion(string $package): ?string
    {
        if (! InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($package);
    }

    private function applicationHost(): ?string
    {
        $url = config('app.url');
        if (! is_string($url) || blank($url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        return $parts['host'].(isset($parts['port']) ? ":{$parts['port']}" : '');
    }

    private function viewsAreCached(): bool
    {
        $compiledViewPath = config('view.compiled');
        if (! is_string($compiledViewPath) || ! is_dir($compiledViewPath)) {
            return false;
        }

        return count(glob($compiledViewPath.'/*.php') ?: []) > 0;
    }

    private function canLoadSchoolInfos(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin',
            'register_admin',
            'tutoring_admin',
            'teaching_admin',
            'materials_admin',
            'materials_moderator',
            'teacher',
            'lunch_admin',
            'studentstimetables_admin',
            'studentstimetables_moderator',
        ]);
    }

    private function canLoadEnvironmentVersions(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function registerStep1(RegisterStep1Request $request)
    {
        $adminService = new AdminService;
        $validated = $request->validated();

        $user = $adminService->checkRegister($validated['data']);
        if (! $user) {
            $user = $adminService->createRegisterUser($validated['data']);
        }

        // Token zusenden
        $adminService->sendRegisterToken($user, $validated['data']['email'], 1);
        $data = ['step' => 'REGISTER_ENTER_TOKEN'];

        return response()->json($data, 200);
    }

    public function registerStep2(RegisterStep2Request $request)
    {
        $adminService = new AdminService;
        $validated = $request->validated();

        $user = $adminService->checkRegister($validated['data']);

        if (! $user) {
            abort(401, 'Registrieren funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        // E-Mail ist somit verifiziert!
        $user->email_verified_at = now();
        $user->save();

        $data = ['step' => 'REGISTER_ENTER_FIELDS'];

        return response()->json($data, 200);
    }

    public function registerStep3(RegisterStep3Request $request)
    {
        $adminService = new AdminService;

        $validated = $request->validated();

        $user = $adminService->checkRegister($validated['data']);

        if (! $user || ! $user->consumeToken2Fa($validated['data']['token_2fa'])) {
            abort(401, 'Registrieren funktioniert nicht. Code falsch oder Zeit abgelaufen.');
        }

        $user = $adminService->updateRegisterUser($user, $validated['data']);

        $data = ['step' => $user->confirmed_at ? 'REGISTER_FINISHED' : 'REGISTER_MUST_BE_CONFIRMED'];

        return response()->json($data, 200);
    }

    public function passwordUnknownStepEmail(PasswordUnknownStepEmailRequest $request, AdminService $service)
    {
        $adminService = new AdminService;
        $validated = $request->validated();

        $data = $adminService->checkEmail($validated['data']);

        if ($data['users_count'] == 0) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        if ($data['step'] == 'LOGIN_ENTER_PASSWORD') {
            $user = User::where('school_id', $data['school_id'])->where('email', $data['email'])->first();
            $service->passwordUnkownSendToken($data);
            $data['step'] = 'PASSWORD_UNKNOWN_ENTER_TOKEN';
        }
        if ($data['step'] == 'LOGIN_SELECT_SCHOOL') {
            $data['step'] = 'PASSWORD_UNKNOWN_SELECT_SCHOOL';
        }

        return response()->json($data, 200);
    }

    public function passwordUnknownStepSchool(AdminPasswordUnknownStepSchoolRequest $request, AdminService $service)
    {
        $validated = $request->validated();
        $data = $service->passwordUnkownSendToken($validated['data']);
        $data['step'] = 'PASSWORD_UNKNOWN_ENTER_TOKEN';

        return response()->json($data, 200);
    }

    public function passwordUnknownStepToken(AdminPasswordUnknownStepTokenRequest $request, AdminService $service)
    {
        $validated = $request->validated();

        return response()->json($service->loginWithEmailCode($validated['data']))
            ->header('Cache-Control', 'no-store, private');
    }

    public function passwordUnknownStepToken2(AdminPasswordUnknownStepToken2Request $request, AdminService $service)
    {
        $validated = $request->validated();

        return response()->json($service->loginWithEmailCode($validated['data']))
            ->header('Cache-Control', 'no-store, private');
    }

    public function passwordUnknownStepPassword(AdminPasswordUnknownStepPasswordRequest $request, AdminService $service)
    {

        $validated = $request->validated();

        $data = $validated['data'];
        $data = $service->passwordUnkownCheckToken($data);

        if (! $user = User::where('email', $data['email'])->where('school_id', $data['school_id'])->first()) {
            abort(404, 'Kein Benutzer gefunden');
        }
        if ($user->is_2fa) {
            $data = $service->passwordUnkownCheckToken2($data);
        }
        $data = $service->passwordUnkownSetPassword($data);
        $data['step'] = 'PASSWORD_UNKNOWN_FINISHED';

        return response()->json($data, 200);
    }

    public function loginStepEmail(LoginStepEmailRequest $request)
    {
        $adminService = new AdminService;
        $validated = $request->validated();

        $data = $adminService->checkEmail($validated['data']);

        if ($data['users_count'] == 0) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        return response()->json($data, 200);
    }

    public function loginStep2(LoginStep2Request $request)
    //
    {
        $adminService = new AdminService;
        $validated = $request->validated();

        $data = $adminService->checkLogin($validated['data']);

        if ($data['step'] !== 'LOGIN_SUCCESS') {
            $data = $adminService->check2Fa($data);
        }

        if ($data['step'] === 'LOGIN_SUCCESS') {
            $adminService->login($data);
        }

        unset($data['password']);

        return response()->json($data, 200);
    }

    public function loginStep3(LoginStep3Request $request)
    {
        $adminService = new AdminService;
        $validated = $request->validated();

        $data = $adminService->checkLogin($validated['data']);
        $user = $adminService->login2Fa($data);
        unset($data['password']);
        unset($data['token_2fa']);

        $data = [
            'step' => 'LOGIN_SUCCESS',
            'auth' => true,
            'user' => $user,
        ];

        return response()->json($data, 200);
    }

    public function executeLogout(Request $request)
    {

        if (Auth::check()) {
            Auth::guard('web')->logout();
            session()->invalidate();
            // session()->regenerateToken();
        }

        $data = $this->getConfigData();

        return response()->json($data, 200);
    }

    public function loadRoles(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $roles = Role::query()->orderBy('name')->get();

        return response()->json(RoleResource::collection($roles), 200);
    }

    public function newTeacherStepEmail(AdminNewTeacherStepEmailRequest $request, TeacherListService $service)
    {
        $validated = $request->validated();
        $email = $validated['email'];

        $data = $service->getAllTeachersNotInUsers($email);

        // EMail ist berechtigt, sich als Lehrer anzumelden
        if ($data['step'] == 'NEW_TEACHER_NO_TEACHER') {
            abort(404, 'Eine Anmeldung als neue:r Lehrer:in ist mit dieser E-Mail nicht möglich');
        }
        if ($data['step'] == 'NEW_TEACHER_INPUT_CODE') {
            $service->sendCode($data['school']['id'], $data['email']);
        }

        return response()->json($data, 200);
    }

    public function newTeacherStepSchool(AdminNewTeacherStepSchoolRequest $request, TeacherListService $service)
    {
        $validated = $request->validated();
        $email = $validated['email'];
        $school_id = $validated['school_id'];
        $school = School::findOrFail($school_id);
        $validated['school'] = new SchoolResource($school);

        $service->sendCode($school_id, $email);

        $validated['step'] = 'NEW_TEACHER_INPUT_CODE';

        return response()->json($validated, 200);
    }

    public function newTeacherStepCode(AdminNewTeacherStepCodeRequest $request, TeacherListService $service)
    {

        $validated = $request->validated();
        $email = $validated['email'];
        $school_id = $validated['school_id'];
        $code = $validated['token'];

        if (! $teacher = Teacher::where('school_id', $school_id)->where('email', $email)->first()) {
            abort(404, 'Kein passender Lehrer in der Liste gefunden.');
        }

        if (! $service->consumeToken($teacher, $code)) {
            // Token hat nicht gestimmt oder ist abgelaufen
            $validated['step'] = 'NEW_TEACHER_TOKEN_WRONG';
        } else {
            // Alles ok, User erzeugen und login
            $user = $service->createUserFromTeacher($validated);
            $user->assignRole('teacher');
            Auth::guard('web')->login($user, true);
            session()->regenerate();
            $validated['step'] = 'NEW_TEACHER_OK';
        }

        return response()->json($validated, 200);
    }
}
