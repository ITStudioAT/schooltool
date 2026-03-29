<?php

namespace App\Services;

use App\Models\User;
use App\Traits\HasRoleTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AdminNavigationService
{
    use HasRoleTrait;

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
    ];

    /* MENÜ AUF DER LINKEN SEITE */
    public function dashboardMenu(): array
    {
        $menu = [];
        if (! Auth::check()) {
            return [];
        }

        $user = User::findOrFail(Auth::user()->id);
        $user_name = substr($user->last_name.' '.$user->first_name, 0, 17);
        $isSuperAdmin = $this->userHasRole(['super_admin']);

        $menu[] = ['title' => 'Home', 'icon' => 'mdi-home', 'to' => '/admin', 'is_active' => true];
        if ($isSuperAdmin || $user->hasAnyRole(self::ADMIN_SHELL_ROLES)) {
            $menu[] = ['title' => 'Einstellungen', 'icon' => 'mdi-cog', 'to' => '/admin/settings', 'is_active' => true];
        }

        $registerLicenceStatus = $this->toolAccessStatus($user, 'Anmeldetool', ['admin', 'register_admin']);
        $tutoringLicenceStatus = $this->toolAccessStatus($user, 'Nachhilfetool', ['admin', 'tutoring_admin', 'teacher']);
        $teachingLicenceStatus = $this->toolAccessStatus($user, 'Lehrertool', ['admin', 'teaching_admin', 'teacher']);
        $materialsLicenceStatus = $this->toolAccessStatus($user, 'Materialientool', ['admin', 'materials_admin', 'materials_moderator']);
        $restaurantLicenceStatus = $this->toolAccessStatus($user, 'Restaurant', ['admin', 'lunch_admin']);
        $abaLicenceStatus = $this->toolAccessStatus($user, 'ABA', ['aba_teacher']);
        // ANMELDESYSTEM
        if ($this->userHasRole(['admin', 'register_admin'])) {
            if ($registerLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Anmeldetool',
                    'icon' => 'mdi-calendar-cursor',
                    'to' => '/admin/register_system',
                    'is_active' => ($registerLicenceStatus === 'active' && config('schooltool.register_active', true)),
                ] + $this->moduleStatusMeta($registerLicenceStatus, 'Anmeldetool');
            }
        }

        // TUTORING
        if ($this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            if ($tutoringLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Nachhilfe',
                    'icon' => 'mdi-cast-education',
                    'to' => '/admin/tutoring',
                    'is_active' => ($tutoringLicenceStatus === 'active' && config('schooltool.tutoring_active', false)),
                ] + $this->moduleStatusMeta($tutoringLicenceStatus, 'Nachhilfe');
            }
        }

        // TEACHER

        if ($this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            if ($teachingLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Unterricht',
                    'icon' => 'mdi-school',
                    'to' => '/admin/teaching',
                    'is_active' => ($teachingLicenceStatus === 'active' && config('schooltool.teaching_active', false)),
                ] + $this->moduleStatusMeta($teachingLicenceStatus, 'Unterricht');
            }
        }

        // ABA
        if ($this->userHasRole(['aba_teacher']) && $abaLicenceStatus === 'active') {
            $menu[] = [
                'title' => 'ABA',
                'icon' => 'mdi-certificate-outline',
                'to' => '/admin/aba',
                'is_active' => true,
            ];
        }

        // MATERIALS
        if ($this->userHasRole(['admin', 'materials_admin', 'materials_moderator'])) {
            if ($materialsLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Materialien',
                    'icon' => 'mdi-folder-multiple-outline',
                    'to' => '/admin/materials',
                    'is_active' => ($materialsLicenceStatus === 'active' && config('schooltool.materials_active', false)),
                ] + $this->moduleStatusMeta($materialsLicenceStatus, 'Materialien');
            }
        }

        // RESTAURANT
        if ($this->userHasRole(['admin', 'lunch_admin'])) {
            if ($restaurantLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Restaurant',
                    'icon' => 'mdi-silverware-fork-knife',
                    'to' => '/admin/restaurant',
                    'is_active' => $restaurantLicenceStatus === 'active',
                ] + $this->moduleStatusMeta($restaurantLicenceStatus, 'Restaurant');
            }
        }

        // ABMELDEN
        $menu[] = ['title' => 'Abmelden', 'icon' => 'mdi-power-cycle', 'click' => 'logout', 'is_active' => true];

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
            'aba' => false,
        ];

        if (! $user) {
            return $capabilities;
        }

        $menuByPath = collect($menu)
            ->filter(fn (array $item) => isset($item['to']) && is_string($item['to']))
            ->keyBy('to');

        $capabilities['home'] = $user->hasAdminShellAccess();
        $capabilities['settings'] = $user->hasAdminShellAccess();
        $capabilities['profile'] = $capabilities['home'];
        $capabilities['users'] = $user->hasAnyRole(['admin', 'super_admin']);
        $capabilities['user_roles'] = $user->hasRole('super_admin');
        $capabilities['super_admin'] = $user->hasAnyRole(['admin', 'super_admin']);
        $capabilities['register_system'] = $this->menuRouteCapability($user, $menuByPath, '/admin/register_system', ['admin', 'register_admin']);
        $capabilities['tutoring'] = $this->menuRouteCapability($user, $menuByPath, '/admin/tutoring', ['admin', 'tutoring_admin', 'teacher']);
        $capabilities['teaching'] = $this->menuRouteCapability($user, $menuByPath, '/admin/teaching', ['admin', 'teaching_admin', 'teacher']);
        $capabilities['materials'] = $this->menuRouteCapability($user, $menuByPath, '/admin/materials', ['admin', 'materials_admin', 'materials_moderator']);
        $capabilities['groups'] = false;
        $capabilities['restaurant'] = $this->menuRouteCapability($user, $menuByPath, '/admin/restaurant', ['admin', 'lunch_admin']);
        $capabilities['aba'] = $this->menuRouteCapability($user, $menuByPath, '/admin/aba', ['aba_teacher'], false);

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

    private function toolAccessStatus(?User $user, string $licenceName, array $allowedRoles = []): string
    {
        if (! $user) {
            return 'missing';
        }

        return app(LicenceService::class)->toolAccessStatusForUser($user, $user->selectedSchool, $licenceName, $allowedRoles);
    }

    private function moduleStatusMeta(string $status, string $moduleLabel): array
    {
        if ($status === 'expired') {
            return [
                'status_icon' => 'mdi-clock-alert-outline',
                'status_color' => 'warning',
                'status_title' => $moduleLabel.': Lizenz abgelaufen',
            ];
        }

        if ($status === 'missing') {
            return [
                'status_icon' => 'mdi-alert-circle-outline',
                'status_color' => 'error',
                'status_title' => $moduleLabel.': Lizenz nicht vorhanden',
            ];
        }

        return [];
    }

    /**
     * Apply the dashboard visibility rules to module routes:
     * - hidden item => route disabled
     * - shown but disabled item => route disabled
     * - shown and enabled item => route allowed
     * Additionally require the correct role for the module.
     */
    private function menuRouteCapability(User $user, Collection $menuByPath, string $path, array $allowedRoles, bool $allowSuperAdmin = true): bool
    {
        $dashboardShow = $menuByPath->has($path);
        $dashboardDisabled = ! (bool) data_get($menuByPath->get($path), 'is_active', false);
        $disableAllWebRoutes = ! $dashboardShow || $dashboardDisabled;

        if ($disableAllWebRoutes) {
            return false;
        }

        if ($allowSuperAdmin && $user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasAnyRole($allowedRoles);
    }
}
