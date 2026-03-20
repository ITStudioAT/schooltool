<?php

namespace App\Services;

use App\Models\User;
use App\Traits\HasRoleTrait;

class AdminNavigationService
{
    use HasRoleTrait;

    /* MENÜ AUF DER LINKEN SEITE */
    public function dashboardMenu(): array
    {
        $menu = [];
        if (! auth()->check()) {
            return [];
        }

        $user = User::findOrFail(auth()->user()->id);
        $user_name = substr($user->last_name.' '.$user->first_name, 0, 17);

        $menu[] = ['title' => 'Home', 'icon' => 'mdi-home', 'to' => '/admin'];

        // DASHBOARD
        if ($this->userHasRole(['admin'])) {
            $menu[] = ['title' => 'Dashboard', 'icon' => 'mdi-view-dashboard', 'to' => '/admin/dashboard'];
        }

        // BENUTZER ALS admin
        if ($this->userHasRole(['admin'])) {
            $menu[] = ['title' => 'Benutzer/Rollen', 'icon' => 'mdi-account-multiple', 'to' => '/admin/users'];
        }

        // PROFILE
        $menu[] = ['title' => $user_name, 'icon' => 'mdi-account', 'to' => '/admin/profile'];

        // ABMELDEN
        $menu[] = ['title' => 'Abmelden', 'icon' => 'mdi-power-cycle', 'click' => 'logout'];

        return $menu;
    }

    /* MENÜ PROFILE */
    public function profileMenu(): array
    {
        $menu = [];
        if ($this->userHasRole(['admin', 'user'])) {
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
}
