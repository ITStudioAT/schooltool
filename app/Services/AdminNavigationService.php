<?php

namespace App\Services;

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

        $menu[] = ['title' => 'Home', 'icon' => 'mdi-home', 'to' => '/admin', 'is_active' => true];

        // SUPERADMIN
        if ($this->userHasRole(['super_admin'])) {
            $menu[] = ['title' => 'Super-Admin', 'icon' => 'mdi-shield-crown', 'to' => '/admin/super_admin', 'is_active' => true];
        }

        // ANMELDESYSTEM
        if ($this->userHasRole(['admin', 'register_admin'])) {
            $menu[] = ['title' => 'Anmeldetool', 'icon' => 'mdi-calendar-cursor', 'to' => '/admin/register_system', 'is_active' => true];
        }

        // TUTORING
        if ($this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            $menu[] = ['title' => 'Nachhilfe', 'icon' => 'mdi-cast-education', 'to' => '/admin/tutoring', 'is_active' => config('schooltool.tutoring_active')];
        }

        // TEACHER
        /*
        if ($this->userHasRole(['admin', 'teacher'])) {
            $menu[] = ['title' => 'Lehrer', 'icon' => 'mdi-school', 'to' => '/admin/teacher', 'is_active' => false];
        }
            */



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
}
