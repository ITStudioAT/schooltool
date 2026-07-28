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
    public function deleteBookings($user, $bookings, $notify, bool $enforceOwnership = false)
    {

        $school_id = $user->school_id;
        $schoolyear_id = $user->schoolyear_id;
        $register_id = $user->register_id;

        $school = School::findOrFail($school_id);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/'.$school->logo),
            'subject' => 'Stornierung Termin',
            'markdown' => 'mails.admin.deleteRegisterDateBooking',
        ];

        // Bookings durchlesen
        foreach ($bookings as $register_date_booking_id) {
            $booking = RegisterDateBooking::query()
                ->where('school_id', $school_id)
                ->where('register_id', $register_id)
                ->when($enforceOwnership, fn ($query) => $query->where('user_id', $user->id))
                ->findOrFail($register_date_booking_id);

            if ($notify) {
                // Wenn gewünscht Abmelde-E-Mail schicken
                $mail['register_name'] = $booking->register->name;
                $mail['student_last_name'] = $booking->student_last_name;
                $mail['student_first_name'] = $booking->student_first_name;
                $mail['note'] = $booking->note;
                $mail['siblings'] = $booking->siblings ?? [];
                $mail['date'] = $booking->registerDate->date;
                $mail['from'] = $booking->registerDate->from;
                $mail['to'] = $booking->registerDate->to;
                Notification::route('mail', EmailAliasResolver::resolveConfigured($booking->user->email))->notify(new StandardEmail($mail));
            }

            // Buchung löschen
            $booking->delete();
        }
    }

    public function updateOrCreateUser($school_id, $validated): User
    {
        $user = User::where('school_id', $school_id)->where('email', $validated['email'])->first();
        if (! $user) {
            $validated['school_id'] = $school_id;
            $validated['password'] = Hash::make(now());
            $user = User::create($validated);

            $user->email_verified_at = now();
            $user->confirmed_at = now();
            $user->is_active = 1;
            $user->save();
        } else {
            $user->update($validated);

            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
            if (! $user->confirmed_at) {
                $user->confirmed_at = now();
            }
            $user->save();
        }
        $user->assignRole('register_user');

        return $user;
    }

    public function createBooking($school_id, $schoolyear_id, $register_id, $user_id, $validated): RegisterDateBooking
    {
        $validated['school_id'] = $school_id;
        $validated['schoolyear_id'] = $schoolyear_id;
        $validated['register_id'] = $register_id;
        $validated['user_id'] = $user_id;

        $is_notify = $validated['is_notify'] ?? false;
        unset($validated['is_notify']);

        $booking = RegisterDateBooking::create($validated);

        $school = $booking->school;

        if ($is_notify) {

            $mail = [
                'from_address' => config('schooltool.noreply_email'),
                'from_name' => $school->long_name,
                'logo' => asset('/storage/images/'.$school->logo),
                'subject' => 'Buchung Termin',
                'markdown' => 'mails.admin.bookRegisterDateBooking',
            ];

            $mail['register_name'] = $booking->register->name;
            $mail['student_last_name'] = $booking->student_last_name;
            $mail['student_first_name'] = $booking->student_first_name;
            $mail['note'] = $booking->note;
            $mail['siblings'] = $booking->siblings ?? [];
            $mail['date'] = $booking->registerDate->date;
            $mail['from'] = $booking->registerDate->from;
            $mail['to'] = $booking->registerDate->to;
            Notification::route('mail', EmailAliasResolver::resolveConfigured($booking->user->email))->notify(new StandardEmail($mail));
        }

        return $booking;
    }
}
