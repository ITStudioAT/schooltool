<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminNavigationService;

class NavigationController extends Controller
{
    public function profileMenu()
    {
        $navigationService = new AdminNavigationService();

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $data = [
            'menu' => $navigationService->profileMenu(),
        ];

        return response()->json($data, 200);
    }

    public function userMenu()
    {
        $navigationService = new AdminNavigationService();

        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $data = [
            'menu' => $navigationService->userMenu(),
            'selection' => $navigationService->userSelection(),
        ];

        return response()->json($data, 200);
    }
}
