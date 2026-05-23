<?php

namespace App\Services;

use App\Models\User;
use App\Traits\HasRoleTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AdminNavigationService
{
    use HasRoleTrait;

    private const REGISTER_DASHBOARD_ROLES = ['register_admin'];

    private const TUTORING_DASHBOARD_ROLES = ['tutoring_admin', 'teacher'];

    private const TEACHING_DASHBOARD_ROLES = ['admin', 'teaching_admin', 'teacher'];

    private const MATERIALS_DASHBOARD_ROLES = ['materials_admin', 'materials_moderator'];

    private const RESTAURANT_DASHBOARD_ROLES = ['lunch_admin'];

    private const STUDENTS_TIMETABLES_DASHBOARD_ROLES = ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'];

    private const ABA_DASHBOARD_ROLES = ['aba_teacher'];

    private const ADMIN_SHELL_ROLES = [
        'admin',
        'register_admin',
        'tutoring_admin',
        'teaching_admin',
        'materials_admin',
        'materials_moderator',
        'teacher',
        'lunch_admin',
        'aba_teacher',
        'studentstimetables_admin',
        'studentstimetables_moderator',
    ];

    /* MENÜ AUF DER LINKEN SEITE */
    public function dashboardMenu(): array
    {
        $menu = [];
        if (! Auth::check()) {
            return [];
        }

        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing(['roles', 'selectedSchool.schoolTool']);
        $user_name = substr($user->last_name.' '.$user->first_name, 0, 17);
        $isSuperAdmin = $this->userHasRole(['super_admin']);
        $moduleStatusService = app(SchoolToolModuleStatusService::class);
        $licenceStatuses = app(LicenceService::class)->toolAccessStatusesForUser($user, $user->selectedSchool, [
            'Anmeldetool' => self::REGISTER_DASHBOARD_ROLES,
            'Nachhilfetool' => self::TUTORING_DASHBOARD_ROLES,
            'Lehrertool' => self::TEACHING_DASHBOARD_ROLES,
            'Materialientool' => self::MATERIALS_DASHBOARD_ROLES,
            'Restaurant' => self::RESTAURANT_DASHBOARD_ROLES,
            'StudentsTimetables' => self::STUDENTS_TIMETABLES_DASHBOARD_ROLES,
            'ABA' => self::ABA_DASHBOARD_ROLES,
        ]);

        $menu[] = ['title' => 'Home', 'icon' => 'mdi-home', 'to' => '/admin', 'active_paths' => ['/admin'], 'active_exact' => true, 'is_active' => true];
        if ($isSuperAdmin || $user->hasAnyRole(self::ADMIN_SHELL_ROLES)) {
            $menu[] = ['title' => 'Einstellungen', 'icon' => 'mdi-cog', 'to' => '/admin/settings', 'active_paths' => ['/admin/settings'], 'is_active' => true];
        }

        $registerLicenceStatus = $licenceStatuses['Anmeldetool'] ?? 'missing';
        $tutoringLicenceStatus = $licenceStatuses['Nachhilfetool'] ?? 'missing';
        $teachingLicenceStatus = $licenceStatuses['Lehrertool'] ?? 'missing';
        $materialsLicenceStatus = $licenceStatuses['Materialientool'] ?? 'missing';
        $restaurantLicenceStatus = $licenceStatuses['Restaurant'] ?? 'missing';
        $studentsTimetablesLicenceStatus = $licenceStatuses['StudentsTimetables'] ?? 'missing';
        $abaLicenceStatus = $licenceStatuses['ABA'] ?? 'missing';
        $registerModuleStatus = $moduleStatusService->userStatusForModule('register', $user->selectedSchool);
        $tutoringModuleStatus = $moduleStatusService->userStatusForModule('tutoring', $user->selectedSchool);
        $teachingModuleStatus = $moduleStatusService->userStatusForModule('teaching', $user->selectedSchool);
        $materialsModuleStatus = $moduleStatusService->userStatusForModule('materials', $user->selectedSchool);
        $restaurantModuleStatus = $moduleStatusService->userStatusForModule('restaurant', $user->selectedSchool);
        $studentsTimetablesModuleStatus = $moduleStatusService->userStatusForModule('students_timetables', $user->selectedSchool);
        $abaModuleStatus = $moduleStatusService->userStatusForModule('aba', $user->selectedSchool);
        // ANMELDESYSTEM
        if ($user->hasAnyRole(self::REGISTER_DASHBOARD_ROLES)) {
            if ($registerLicenceStatus !== 'missing' && $moduleStatusService->adminVisibleForModule('register', $user->selectedSchool)) {
                $menu[] = [
                    'title' => 'Anmeldetool',
                    'icon' => 'mdi-calendar-cursor',
                    'to' => '/admin/register_system',
                    'active_paths' => ['/admin/register_system'],
                    'is_active' => ($registerLicenceStatus === 'active'),
                ] + $this->dashboardStatusMeta($registerLicenceStatus, $registerModuleStatus, 'Anmeldetool');
            }
        }

        // TUTORING
        if ($user->hasAnyRole(self::TUTORING_DASHBOARD_ROLES)) {
            if ($tutoringLicenceStatus !== 'missing' && $moduleStatusService->adminVisibleForModule('tutoring', $user->selectedSchool)) {
                $menu[] = [
                    'title' => 'Nachhilfe',
                    'icon' => 'mdi-cast-education',
                    'to' => '/admin/tutoring',
                    'active_paths' => ['/admin/tutoring'],
                    'is_active' => ($tutoringLicenceStatus === 'active'),
                ] + $this->dashboardStatusMeta($tutoringLicenceStatus, $tutoringModuleStatus, 'Nachhilfe');
            }
        }

        // TEACHER

        if ($user->hasAnyRole(self::TEACHING_DASHBOARD_ROLES)) {
            if ($teachingLicenceStatus !== 'missing' && $moduleStatusService->adminVisibleForModule('teaching', $user->selectedSchool)) {
                $menu[] = [
                    'title' => 'Unterricht',
                    'icon' => 'mdi-school',
                    'to' => '/admin/teaching',
                    'active_paths' => ['/admin/teaching'],
                    'is_active' => ($teachingLicenceStatus === 'active'),
                ] + $this->dashboardStatusMeta($teachingLicenceStatus, $teachingModuleStatus, 'Unterricht');
            }
        }

        // ABA
        if ($user->hasAnyRole(self::ABA_DASHBOARD_ROLES)) {
            if ($abaLicenceStatus !== 'missing' && $moduleStatusService->adminVisibleForModule('aba', $user->selectedSchool)) {
                $menu[] = [
                    'title' => 'ABA',
                    'icon' => 'mdi-certificate-outline',
                    'to' => '/admin/aba',
                    'active_paths' => ['/admin/aba'],
                    'is_active' => ($abaLicenceStatus === 'active'),
                ] + $this->dashboardStatusMeta($abaLicenceStatus, $abaModuleStatus, 'ABA');
            }
        }

        // MATERIALS
        if ($user->hasAnyRole(self::MATERIALS_DASHBOARD_ROLES)) {
            if ($materialsLicenceStatus !== 'missing' && $moduleStatusService->adminVisibleForModule('materials', $user->selectedSchool)) {
                $menu[] = [
                    'title' => 'Materialien',
                    'icon' => 'mdi-folder-multiple-outline',
                    'to' => '/admin/materials',
                    'active_paths' => ['/admin/materials'],
                    'is_active' => ($materialsLicenceStatus === 'active'),
                ] + $this->dashboardStatusMeta($materialsLicenceStatus, $materialsModuleStatus, 'Materialien');
            }
        }

        // RESTAURANT
        if ($user->hasAnyRole(self::RESTAURANT_DASHBOARD_ROLES)) {
            if ($restaurantLicenceStatus !== 'missing' && $moduleStatusService->adminVisibleForModule('restaurant', $user->selectedSchool)) {
                $menu[] = [
                    'title' => 'Restaurant',
                    'icon' => 'mdi-silverware-fork-knife',
                    'to' => '/admin/restaurant',
                    'is_active' => ($restaurantLicenceStatus === 'active'),
                    'active_paths' => ['/admin/restaurant'],
                ] + $this->dashboardStatusMeta($restaurantLicenceStatus, $restaurantModuleStatus, 'Restaurant');
            }
        }

        // STUDENTS TIMETABLES
        if ($user->hasAnyRole(self::STUDENTS_TIMETABLES_DASHBOARD_ROLES)) {
            if ($studentsTimetablesLicenceStatus !== 'missing' && $moduleStatusService->adminVisibleForModule('students_timetables', $user->selectedSchool)) {
                $menu[] = [
                    'title' => 'Schülerstundenpläne',
                    'icon' => 'mdi-calendar-clock',
                    'to' => '/admin/students-timetables',
                    'active_paths' => ['/admin/students-timetables'],
                    'is_active' => ($studentsTimetablesLicenceStatus === 'active'),
                ] + $this->dashboardStatusMeta($studentsTimetablesLicenceStatus, $studentsTimetablesModuleStatus, 'Schülerstundenpläne');
            }
        }

        // DOKUMENTATION
        $menu[] = ['title' => 'Dokumentation', 'icon' => 'mdi-book-open-variant', 'href' => '/documentation/index.html', 'is_active' => true];

        // ABMELDEN
        $menu[] = ['title' => 'Abmelden', 'icon' => 'mdi-power-cycle', 'click' => 'logout', 'is_active' => true];
        $hopperDashboardMenu = $this->hopperDashboardMenu($user);
        if ($hopperDashboardMenu !== null) {
            $menu[] = $hopperDashboardMenu;
        }

        return $menu;
    }

    public function routeCapabilities(?User $user, array $menu): array
    {
        $capabilities = [
            'home' => false,
            'settings' => false,
            'profile' => false,
            'users' => false,
            'user_roles' => false,
            'super_admin' => false,
            'register_system' => false,
            'tutoring' => false,
            'teaching' => false,
            'materials' => false,
            'groups' => false,
            'restaurant' => false,
            'students_timetables' => false,
            'aba' => false,
        ];

        if (! $user) {
            return $capabilities;
        }

        $menuByPath = $this->dashboardMenuByPath($menu);

        $capabilities['home'] = $user->hasAdminShellAccess();
        $capabilities['settings'] = $user->hasAdminShellAccess();
        $capabilities['profile'] = $capabilities['home'];
        $capabilities['users'] = $user->hasAnyRole(['admin', 'super_admin']);
        $capabilities['user_roles'] = $user->hasRole('super_admin');
        $capabilities['super_admin'] = $user->hasAnyRole(['admin', 'super_admin']);
        $capabilities['register_system'] = $this->menuRouteCapability($user, $menuByPath, '/admin/register_system', self::REGISTER_DASHBOARD_ROLES);
        $capabilities['tutoring'] = $this->menuRouteCapability($user, $menuByPath, '/admin/tutoring', self::TUTORING_DASHBOARD_ROLES);
        $capabilities['teaching'] = $this->menuRouteCapability($user, $menuByPath, '/admin/teaching', self::TEACHING_DASHBOARD_ROLES);
        $capabilities['materials'] = $this->menuRouteCapability($user, $menuByPath, '/admin/materials', self::MATERIALS_DASHBOARD_ROLES);
        $capabilities['groups'] = false;
        $capabilities['restaurant'] = $this->menuRouteCapability($user, $menuByPath, '/admin/restaurant', self::RESTAURANT_DASHBOARD_ROLES);
        $capabilities['students_timetables'] = $this->menuRouteCapability($user, $menuByPath, '/admin/students-timetables', self::STUDENTS_TIMETABLES_DASHBOARD_ROLES);
        $capabilities['aba'] = $this->menuRouteCapability($user, $menuByPath, '/admin/aba', self::ABA_DASHBOARD_ROLES);

        return $capabilities;
    }

    /* MENÜ PROFILE */
    public function profileMenu(): array
    {
        $menu = [];
        if (auth()->user()?->hasAdminShellAccess()) {
            $menu[] = ['title' => '', 'subtitle' => 'Home', 'icon' => 'mdi-home', 'color' => 'secondary',  'to' => '/admin'];
            $menu[] = ['title' => '', 'subtitle' => 'Kennwort ändern', 'icon' => 'mdi-form-textbox-password', 'color' => 'secondary',  'action' => 'wantToChangePassword'];
            $menu[] = ['title' => '', 'subtitle' => '2-FA-Authentifizierung', 'icon' => 'mdi-two-factor-authentication', 'color' => 'secondary',  'action' => 'wantToChange2Fa'];
        }

        return $menu;
    }

    public function userMenu(): array
    /* BENUTZER/ROLLEN: Menü ganz oben */
    {
        $menu = [];
        $menu[] = ['title' => '', 'subtitle' => 'Home', 'icon' => 'mdi-home', 'color' => 'secondary',  'to' => '/admin'];
        if ($this->userHasRole(['super_admin'])) {
            $menu[] = ['title' => '', 'subtitle' => 'Rollen', 'icon' => 'mdi-badge-account-horizontal-outline', 'color' => 'secondary',  'to' => '/admin/users/roles'];
        }

        return $menu;
    }

    /* BENUTZER/ROLLEN: Informationsblöcke */
    public function userSelection(): array
    {
        $userService = new UserService;
        $selection = [];
        $all_users = ['title' => 'Alle Benutzer', 'icon' => 'mdi-account-group', 'url' => '/admin/users/all_users', 'infos' => $userService->allUsersInfos()];

        if ($this->userHasRole(['admin'])) {
            $selection[] = $all_users;
        }

        return $selection;
    }

    private function dashboardStatusMeta(string $licenceStatus, string $moduleStatus, string $moduleLabel): array
    {
        if ($licenceStatus === 'expired') {
            return [
                'status_icon' => 'mdi-clock-alert-outline',
                'status_color' => 'warning',
                'status_title' => $moduleLabel.': Lizenz abgelaufen',
            ];
        }

        if ($licenceStatus === 'missing') {
            return [
                'status_icon' => 'mdi-alert-circle-outline',
                'status_color' => 'error',
                'status_title' => $moduleLabel.': Lizenz nicht vorhanden',
            ];
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hopperDashboardMenu(User $user): ?array
    {
        $hopperAccounts = app(UserHopperService::class)->loadHopperAccounts($user);

        if ($hopperAccounts === []) {
            return null;
        }

        return [
            'title' => 'Hopp',
            'icon' => 'mdi-swap-horizontal',
            'is_active' => true,
            'children' => collect($hopperAccounts)
                ->map(function (array $account): array {
                    $name = trim((string) ($account['full_name'] ?? ''));
                    $email = trim((string) ($account['email'] ?? ''));
                    $detail = collect([$name, $email])
                        ->filter(fn (string $value) => $value !== '')
                        ->implode(' • ');

                    return [
                        'title' => (string) ($account['school_label'] ?? 'Schule'),
                        'subtitle' => $detail,
                        'icon' => 'mdi-school-outline',
                        'click' => 'switchHopperAccount',
                        'target_user_id' => (int) ($account['id'] ?? 0),
                        'is_active' => true,
                    ];
                })
                ->filter(fn (array $account) => $account['target_user_id'] > 0)
                ->values()
                ->all(),
        ];
    }

    /**
     * Apply the dashboard visibility rules to module routes:
     * - hidden item => route disabled
     * - shown but disabled item => route disabled
     * - shown and enabled item => route allowed
     * Additionally require the correct role for the module.
     */
    private function menuRouteCapability(User $user, Collection $menuByPath, string $path, array $allowedRoles): bool
    {
        $dashboardShow = $menuByPath->has($path);
        $dashboardDisabled = ! (bool) data_get($menuByPath->get($path), 'is_active', false);
        $disableAllWebRoutes = ! $dashboardShow || $dashboardDisabled;

        if ($disableAllWebRoutes) {
            return false;
        }

        return $user->hasAnyRole($allowedRoles);
    }

    private function dashboardMenuByPath(array $menu): Collection
    {
        return collect($menu)
            ->filter(fn (array $item) => isset($item['to']) && is_string($item['to']))
            ->flatMap(function (array $item): array {
                $activePaths = data_get($item, 'active_paths', []);
                if (! is_array($activePaths)) {
                    $activePaths = [];
                }

                return collect(array_merge([$item['to']], $activePaths))
                    ->filter(fn (mixed $path) => is_string($path) && $path !== '')
                    ->mapWithKeys(fn (string $path): array => [$path => $item])
                    ->all();
            });
    }
}
