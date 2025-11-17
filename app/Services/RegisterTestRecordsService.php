<?php

namespace App\Services;

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Carbon\Carbon;

class RegisterTestRecordsService
{


    public function checkRequirement(): bool
    {

        $school = School::where('id', 1)->first();
        if (!$school) return false;

        $schoolyear = Schoolyear::where('id', 1)->where('school_id', 1)->first();
        if (!$schoolyear) return false;

        return true;
    }

    public function checkOrCreateUsers(): bool
    // false: Users exists
    // true: Users created
    {
        $count = User::count();
        if ($count >= 500) return false;

        User::factory()
            ->count(500)
            ->create()
            ->each(function ($user) {
                $user->assignRole('register_user');
            });
        return true;
    }

    public function createRegisterEntries(): bool
    {

        // Erzeugen eines Register-Eintrages
        $register = Register::create([
            'school_id' => 1,
            'schoolyear_id' => 1,
            'name' => 'Gruppengespräche',
            'description_on_website' => 'Liebe Eltern,\n\nwir begrüßen Sie recht herzlich zu den Gruppengesprächen mit Ihren Kindern.\n\nBitte melden Sie hier Ihr Kind zu einem Termin an.\n\nBeste Grüße\nIhr Schulteam',
            'show_phone' => 1,
            'must_phone' => 1,
            'show_student_last_name' => 1,
            'must_student_last_name' => 1,
            'show_student_first_name' => 1,
            'must_student_first_name' => 1,
            'show_student_birthdate' => 1,
            'must_student_birthdate' => 1,

        ]);
        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $nextTuesday = Carbon::now()->next(Carbon::TUESDAY)->toDateString();
        $nextWednesday = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();

        // create Array with 500 users
        $highestId = User::max('id');
        $ids = range($highestId, $highestId - 499);
        $ids = array_filter($ids, fn($id) => $id > 0);

        // MONDAY
        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextMonday, '08:00', '09:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextMonday, '08:00', '09:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextMonday, '09:00', '10:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextMonday, '09:00', '10:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextMonday, '10:00', '11:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextMonday, '10:00', '11:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextMonday, '11:00', '12:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextMonday, '11:00', '12:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        // TUESDAY
        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextTuesday, '08:00', '09:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextTuesday, '08:00', '09:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextTuesday, '09:00', '10:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextTuesday, '09:00', '10:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextTuesday, '10:00', '11:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextTuesday, '10:00', '11:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextTuesday, '11:00', '12:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextTuesday, '11:00', '12:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        // WEDNESDAY
        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextWednesday, '08:00', '09:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextWednesday, '08:00', '09:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextWednesday, '09:00', '10:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextWednesday, '09:00', '10:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextWednesday, '10:00', '11:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextWednesday, '10:00', '11:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 1', $nextWednesday, '11:00', '12:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        $registerDate = $this->createRegisterDate($register->id, 'Gruppe 2', $nextWednesday, '11:00', '12:00', 20);
        $ids = $this->createRegisterDateBooking($registerDate, $ids, 20);

        return true;
    }

    private function createRegisterDateBooking($registerDate, $ids, $count): array
    {

        $count = min($count, count($ids));

        for ($i = 0; $i < $count; $i++) {
            // Always take the next highest available user id so first booking uses the overall max id
            $userId = array_shift($ids);

            $user = User::find($userId);

            // Create the booking
            RegisterDateBooking::create([
                'school_id'       => 1,
                'schoolyear_id'   => 1,
                'register_id'     => $registerDate->register_id,
                'register_date_id' => $registerDate->id,
                'user_id'         => $userId,
                'student_last_name'  => $user->last_name,
                'student_first_name' =>  fake()->firstName(),
                'student_birthdate' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            ]);
        }

        return $ids;
    }



    private function createRegisterDate($register_id, $supervisor, $date, $from, $to, $max_registrations): RegisterDate
    {
        return RegisterDate::create([
            'school_id' => 1,
            'schoolyear_id' => 1,
            'register_id' => $register_id,
            'supervisor' => $supervisor,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'max_registrations' => $max_registrations,
        ]);
    }
}
