<?php

namespace App\Http\Controllers;

use App\Models\TeachingCourse;
use App\Models\User;
use App\Traits\HasRoleTrait;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use HasRoleTrait;

    protected function authorizeTeachingCourseAccess(TeachingCourse $course, User $authUser): void
    {
        if ((int) $course->school_id !== (int) $authUser->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $course->schoolyear_id !== (int) $authUser->schoolyear_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (
            $authUser->hasRole('teacher')
            && ! $authUser->hasAnyRole(['admin', 'teaching_admin'])
            && (int) $course->user_id !== (int) $authUser->id
        ) {
            abort(403, 'Sie haben keine Berechtigung');
        }
    }

    protected function teachingCourseActor(User $authUser, TeachingCourse $course): User
    {
        return $course->user ?: $authUser;
    }
}
