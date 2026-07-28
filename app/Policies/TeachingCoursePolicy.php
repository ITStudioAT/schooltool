<?php

namespace App\Policies;

use App\Models\TeachingCourse;
use App\Models\User;

class TeachingCoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teaching_admin', 'teacher']);
    }

    public function view(User $user, TeachingCourse $course): bool
    {
        return $this->canManage($user, $course);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TeachingCourse $course): bool
    {
        return $this->canManage($user, $course);
    }

    public function delete(User $user, TeachingCourse $course): bool
    {
        return $this->canManage($user, $course);
    }

    private function canManage(User $user, TeachingCourse $course): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ((int) $course->school_id !== (int) $user->school_id) {
            return false;
        }

        if ((int) $course->schoolyear_id !== (int) $user->schoolyear_id) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'teaching_admin'])) {
            return true;
        }

        return $user->hasRole('teacher')
            && (int) $course->user_id === (int) $user->id;
    }
}
