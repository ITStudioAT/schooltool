<?php

namespace App\Services;

use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;




class RegisterDateBookingService
{

    public function deleteBookings($user, $bookings, $notify)
    {

        $school_id = $user->school_id;
        $schoolyear_id = $user->schoolyear_id;
        $register_id = $user->register_id;

        $school = School::findOrFail($school_id);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => 'Stornierung Termin',
            'markdown' => 'mails.admin.deleteRegisterDateBooking'
        ];

        // Bookings durchlesen
        foreach ($bookings as $register_date_booking_id) {
            $booking = RegisterDateBooking::where('school_id', $school_id)->where('schoolyear_id', $schoolyear_id)->where('register_id', $register_id)->where('id', $register_date_booking_id)->first();


            if ($notify) {
                // Wenn gewünscht Abmelde-E-Mail schicken
                $mail['register_name'] = $booking->register->name;
                $mail['student_last_name'] = $booking->student_last_name;
                $mail['student_first_name'] = $booking->student_first_name;
                $mail['date'] = $booking->registerDate->date;
                $mail['from'] = $booking->registerDate->from;
                $mail['to'] = $booking->registerDate->to;
                Notification::route('mail', $booking->user->email)->notify(new StandardEmail($mail));
            }

            // Buchung löschen
            $booking->delete();
        }
    }

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
