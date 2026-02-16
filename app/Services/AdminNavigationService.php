<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\User;
use App\Services\UserService;
use App\Traits\HasRoleTrait;
use Illuminate\Support\Facades\Auth;

class AdminNavigationService
{
    use HasRoleTrait;

    /* MENÜ AUF DER LINKEN SEITE */
    public function dashboardMenu(): array
    {
        $menu = [];
        if (! Auth::check()) {
            return [];
        }

        $user = User::findOrFail(Auth::user()->id);
        $user_name = substr($user->last_name . ' ' . $user->first_name, 0, 17);
        $isSuperAdmin = $this->userHasRole(['super_admin']);

        $menu[] = ['title' => 'Home', 'icon' => 'mdi-home', 'to' => '/admin', 'is_active' => true];

        // SUPERADMIN
        if ($isSuperAdmin) {
            $menu[] = ['title' => 'Super-Admin', 'icon' => 'mdi-shield-crown', 'to' => '/admin/super_admin', 'is_active' => true];
        } else {
            // ADMIN
            if ($this->userHasRole(['admin'])) {
                $menu[] = ['title' => 'Admin', 'icon' => 'mdi-shield-crown', 'to' => '/admin/super_admin', 'is_active' => true];
            }
        }

        $registerLicenceStatus = $this->licenceStatus($user, 'Anmeldetool');
        $tutoringLicenceStatus = $this->licenceStatus($user, 'Nachhilfetool');
        $teachingLicenceStatus = $this->licenceStatus($user, 'Lehrertool');

        // ANMELDESYSTEM
        if ($isSuperAdmin || $this->userHasRole(['admin', 'register_admin'])) {
            if ($isSuperAdmin || $registerLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Anmeldetool',
                    'icon' => 'mdi-calendar-cursor',
                    'to' => '/admin/register_system',
                    'is_active' => $isSuperAdmin ? true : ($registerLicenceStatus === 'active' && config('schooltool.register_active', true)),
                ] + $this->moduleStatusMeta($registerLicenceStatus, 'Anmeldetool');
            }
        }

        // TUTORING
        if ($isSuperAdmin || $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            if ($isSuperAdmin || $tutoringLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Nachhilfe',
                    'icon' => 'mdi-cast-education',
                    'to' => '/admin/tutoring',
                    'is_active' => $isSuperAdmin ? true : ($tutoringLicenceStatus === 'active' && config('schooltool.tutoring_active', false)),
                ] + $this->moduleStatusMeta($tutoringLicenceStatus, 'Nachhilfe');
            }
        }

        // TEACHER

        if ($isSuperAdmin || $this->userHasRole(['admin', 'teacher'])) {
            if ($isSuperAdmin || $teachingLicenceStatus !== 'missing') {
                $menu[] = [
                    'title' => 'Unterricht',
                    'icon' => 'mdi-school',
                    'to' => '/admin/teaching',
                    'is_active' => $isSuperAdmin ? true : ($teachingLicenceStatus === 'active' && config('schooltool.teaching_active', false)),
                ] + $this->moduleStatusMeta($teachingLicenceStatus, 'Unterricht');
            }
        }




        // PROFILE
        $menu[] = ['title' => $user_name, 'icon' => 'mdi-account', 'to' => '/admin/profile', 'is_active' => true];

        // ABMELDEN
        $menu[] = ['title' => 'Abmelden', 'icon' => 'mdi-power-cycle', 'click' => 'logout', 'is_active' => true];

        return $menu;
    }

    /* MENÜ PROFILE */
    public function profileMenu(): array
    {
        $menu = [];
        if ($this->userHasRole(['admin', 'user', 'register_user', 'register_admin'])) {
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
        $userService = new UserService();
        $selection = [];
        $all_users = ['title' => 'Alle Benutzer', 'icon' => 'mdi-account-group', 'url' => '/admin/users/all_users', 'infos' => $userService->allUsersInfos()];

        if ($this->userHasRole(['admin'])) {
            $selection[] = $all_users;
        }

        return $selection;
    }

    private function licenceStatus(?User $user, string $licenceName): string
    {
        if (!$user || !$user->selectedSchool) {
            return 'missing';
        }

        $licence = Licence::where('name', $licenceName)->first();
        if (!$licence) {
            return 'missing';
        }

        $schoolLicence = $user->selectedSchool->licences()->where('licence_id', $licence->id)->first();
        if (!$schoolLicence) {
            return 'missing';
        }

        $validUntil = $schoolLicence->pivot->valid_until;
        if ($validUntil === null || $validUntil >= now()->toDateString()) {
            return 'active';
        }

        return 'expired';
    }

    private function moduleStatusMeta(string $status, string $moduleLabel): array
    {
        if ($status === 'expired') {
            return [
                'status_icon' => 'mdi-clock-alert-outline',
                'status_color' => 'warning',
                'status_title' => $moduleLabel . ': Lizenz abgelaufen',
            ];
        }

        if ($status === 'missing') {
            return [
                'status_icon' => 'mdi-alert-circle-outline',
                'status_color' => 'error',
                'status_title' => $moduleLabel . ': Lizenz nicht vorhanden',
            ];
        }

        return [];
    }
}
