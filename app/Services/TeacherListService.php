<?php

namespace App\Services;

use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TeacherListService
{
    public function create(int $schoolId, array $data): Teacher
    {
        $shortExists = Teacher::where('school_id', $schoolId)
            ->where('short', $data['short'])
            ->exists();

        if ($shortExists) {
            abort(409, 'Das Kurzzeichen wird bereits verwendet');
        }

        $emailExists = Teacher::where('school_id', $schoolId)
            ->where('email', $data['email'])
            ->exists();

        if ($emailExists) {
            abort(409, 'Die E-Mail-Adresse wird bereits verwendet');
        }

        $data['school_id'] = $schoolId;

        return Teacher::create($data);
    }

    public function update(int $schoolId, array $data): Teacher
    {
        $teacher = Teacher::findOrFail($data['id']);

        if ($teacher->school_id !== $schoolId) {
            abort(401, 'Diese Änderung kann nicht durchgeführt werden.');
        }

        if ($data['short'] !== $teacher->short) {
            $shortExists = Teacher::where('school_id', $schoolId)
                ->whereNot('id', $data['id'])
                ->where('short', $data['short'])
                ->exists();

            if ($shortExists) {
                abort(409, 'Das Kurzzeichen des Lehrers existiert bereits.');
            }
        }

        if ($data['email'] !== $teacher->email) {
            $emailExists = Teacher::where('school_id', $schoolId)
                ->whereNot('id', $data['id'])
                ->where('email', $data['email'])
                ->exists();

            if ($emailExists) {
                abort(409, 'Die E-Mail des Lehrers existiert bereits.');
            }
        }

        $teacher->update([
            'short' => $data['short'],
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
        ]);

        return $teacher;
    }

    public function deleteTeachers(int $schoolId, array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteTeacher($schoolId, $id);
        }
    }

    private function deleteTeacher(int $schoolId, int $id): void
    {
        $teacher = Teacher::findOrFail($id);

        if ($teacher->school_id !== $schoolId) {
            return;
        }

        $teacher->delete();
    }

    public function getAllTeachersNotInUsers(string $email): array
    {
        $teachers = Teacher::where('email', $email)->get();

        $teachers = $teachers->filter(function ($teacher) {
            return ! User::where('email', $teacher->email)
                ->where('school_id', $teacher->school_id)
                ->exists();
        });

        if ($teachers->isEmpty()) {
            return [
                'step' => 'NEW_TEACHER_NO_TEACHER',
                'email' => $email,
                'school' => null,
                'school_id' => null,
                'schools' => null,
            ];
        }

        $schoolIds = $teachers->pluck('school_id')->unique();
        $schools = School::whereIn('id', $schoolIds)->get();

        if ($schools->count() === 1) {
            $school = $schools->first();

            return [
                'step' => 'NEW_TEACHER_INPUT_CODE',
                'email' => $email,
                'school' => $school,
                'school_id' => $school->id,
                'schools' => null,
            ];
        }

        return [
            'step' => 'NEW_TEACHER_SELECT_SCHOOL',
            'email' => $email,
            'school' => null,
            'school_id' => null,
            'schools' => $schools,
        ];
    }

    public function sendCode(int $schoolId, string $email): void
    {
        $teacher = Teacher::where('school_id', $schoolId)
            ->where('email', $email)
            ->first();

        $school = School::findOrFail($schoolId);

        $teacher->token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $teacher->token_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $teacher->save();


        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/' . $school->logo),
            'subject' => 'Code zum Bestätigen Ihrer Anmeldung',
            'markdown' => 'mails.admin.sendCode',
            'token_2fa' => $teacher->token,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $email)->notify(new StandardEmail($mail));
    }

    public function checkToken($user, string $shouldToken): bool
    {
        return $user->token === $shouldToken
            && $user->token_expires_at
            && ! $user->token_expires_at->isPast();
    }

    public function createUserFromTeacher(array $data): User
    {
        $teacher = Teacher::where('school_id', $data['school_id'])
            ->where('email', $data['email'])
            ->first();

        $user = User::create([
            'school_id' => $teacher->school_id,
            'short' => $teacher->short,
            'last_name' => $teacher->last_name,
            'first_name' => $teacher->first_name,
            'email' => $teacher->email,
            'password' => Hash::make(now()),
        ]);

        $user->email_verified_at = now();
        $user->confirmed_at = now();
        $user->is_active = 1;
        $user->login_at = now();
        $user->login_ip = request()->ip();
        $user->save();

        return $user;
    }
}
