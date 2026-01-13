<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TeacherService
{
    public function create(int $schoolId, array $data): User
    {
        $shortExists = User::where('school_id', $schoolId)
            ->where('short', $data['short'])
            ->exists();

        if ($shortExists) {
            abort(409, 'Das Kurzzeichen wird bereits verwendet');
        }

        $emailExists = User::where('school_id', $schoolId)
            ->where('email', $data['email'])
            ->exists();

        if ($emailExists) {
            abort(409, 'Die E-Mail-Adresse wird bereits verwendet');
        }

        $data['school_id'] = $schoolId;
        $data['password'] = Hash::make(now());

        $user = User::create($data);
        $user->email_verified_at = now();
        $user->confirmed_at = now();
        $user->is_active = 1;
        $user->save();

        $user->assignRole('teacher');

        return $user;
    }

    public function update($authUser, array $data): User
    {
        $schoolId = $authUser->school_id;
        $user = User::findOrFail($data['id']);

        if ($user->hasRole('super_admin') && $user->id !== $data['id']) {
            abort(401, 'Ein anderer Lehrer kann nicht gespeichert werden, wenn dieser Super-Admin ist.');
        }

        if ($user->school_id !== $schoolId) {
            abort(401, 'Diese Änderung kann nicht durchgeführt werden.');
        }

        if ($data['short'] !== $user->short) {
            $shortExists = User::where('school_id', $schoolId)
                ->whereNot('id', $data['id'])
                ->where('short', $data['short'])
                ->exists();

            if ($shortExists) {
                abort(409, 'Das Kurzzeichen des Lehrers existiert bereits.');
            }
        }

        if ($data['email'] !== $user->email) {
            $emailExists = User::where('school_id', $schoolId)
                ->whereNot('id', $data['id'])
                ->where('email', $data['email'])
                ->exists();

            if ($emailExists) {
                abort(409, 'Die E-Mail des Lehrers existiert bereits.');
            }
        }

        $user->update([
            'short' => $data['short'],
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
        ]);

        return $user;
    }

    public function deleteTeachers(int $schoolId, array $ids): void
    {
        foreach ($ids as $id) {
            $this->deleteTeacher($schoolId, $id);
        }
    }

    private function deleteTeacher(int $schoolId, int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->school_id !== $schoolId) {
            return;
        }

        if ($user->hasDependencies()) {
            return;
        }

        $hasOnlyTeacherRole = $user->roles()->count() === 1 && $user->hasRole('teacher');
        if (! $hasOnlyTeacherRole) {
            return;
        }

        $user->roles()->detach();
        $user->delete();
    }
}
