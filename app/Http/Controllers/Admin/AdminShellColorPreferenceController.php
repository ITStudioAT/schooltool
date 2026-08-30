<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminShellColorPreferenceRequest;
use Illuminate\Http\JsonResponse;

class AdminShellColorPreferenceController extends Controller
{
    public function update(UpdateAdminShellColorPreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->use_school_color_for_admin_ui = $request->validated('use_school_color_for_admin_ui');
        $user->save();

        return response()->json([
            'use_school_color_for_admin_ui' => $user->use_school_color_for_admin_ui,
        ]);
    }
}
