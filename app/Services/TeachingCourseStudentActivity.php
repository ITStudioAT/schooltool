<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;

class TeachingCourseStudentActivity
{
    public function active(TeachingCourseStudent $membership, TeachingCourse $course): bool
    {
        $user = $this->resolve($membership, $course);

        return ! $user || $user->is_active === null || (bool) $user->is_active;
    }

    public function resolve(TeachingCourseStudent $membership, TeachingCourse $course): ?User
    {
        $import = $membership->import116;
        $validImport = $import && (int) $import->school_id === (int) $course->school_id
            && (int) $import->schoolyear_id === (int) $course->schoolyear_id;
        $user = $membership->user;
        if ($membership->import116_id && ! $validImport) {
            $user = null;
        } elseif ($validImport && $user) {
            $forward = $import->user_id ? (int) $import->user_id : null;
            $reverse = $user->import116_id ? (int) $user->import116_id : null;
            if (($forward !== null && $forward !== (int) $user->id)
                || ($reverse !== null && $reverse !== (int) $import->id)
                || ($forward === null && $reverse !== (int) $import->id)) {
                $user = null;
            }
        } elseif ($validImport) {
            $user = $import->user_id ? $import->user : null;
            if ($user?->import116_id && (int) $user->import116_id !== (int) $import->id) {
                $user = null;
            }
        }

        return $user && (int) $user->school_id === (int) $course->school_id ? $user : null;
    }
}
