<?php

namespace App\Services;

use App\Models\RegisterDateBooking;
use App\Models\User;
use Illuminate\Support\Facades\Hash;




class RegisterDateBookingService
{

    public function updateOrCreateUser($school_id, $validated): User
    {
        $user = User::where('school_id', $school_id)->where('email', $validated['email'])->first();
        if (!$user) {
            $validated['school_id'] =  $school_id;
            $validated['email_verified_at'] = now();
            $validated['confirmed_at'] = now();
            $validated['password'] = Hash::make(now());
            $user = User::create($validated);
        } else {
            if (!$user->email_verified_at) $validated['email_verified_at'] = now();
            if (!$user->confirmed_at) $validated['confirmed_at'] = now();
            $user->update($validated);
        }
        $user->assignRole('register_user');

        return $user;
    }

    public function createBooking($auth_user, $user, $validated): RegisterDateBooking
    {
        $validated['school_id'] = $auth_user->school_id;
        $validated['schoolyear_id'] = $auth_user->schoolyear_id;
        $validated['register_id'] = $auth_user->register_id;
        $validated['user_id'] = $user->id;

        $booking = RegisterDateBooking::create($validated);
        return $booking;
    }
}
