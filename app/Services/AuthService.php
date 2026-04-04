<?php

namespace App\Services;

use App\Http\Resources\Admin\RoleResource;
use App\Http\Resources\Homepage\UserResource;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function getAuth()
    {
        $auth = [];

        if (Auth::check()) {
            $user = Auth::user();
            $auth = [
                'is_auth' => true,
                'user' => new UserResource($user),
                'roles' => RoleResource::collection($user->roles),
            ];
        } else {
            $auth = ['is_auth' => false];
        }

        return $auth;
    }
}
