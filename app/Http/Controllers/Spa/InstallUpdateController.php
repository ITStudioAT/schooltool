<?php

namespace App\Http\Controllers\Spa;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Services\InstallUpdateService;

class InstallUpdateController extends Controller
{
    public function index(Request $request)
    {
        $installUpdateService = new InstallUpdateService();

        /* 1. User der Users-Tabelle laden */
        if (! $user = User::query()->first()) {
            abort(404, '1. Benutzer wurde nicht gefunden. Bitte mit php artsian user:create anlegen');
        }

        /* Rollen erzeugen */
        $roles = ['super_admin', 'admin', 'user'];
        $installUpdateService->createRoles($roles);

        /* 1. User super_admin zuweisen */
        $user->assignRole('super_admin');
    }
}
