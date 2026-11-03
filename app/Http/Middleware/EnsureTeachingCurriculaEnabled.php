<?php

namespace App\Http\Middleware;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseDateMaterialAttachment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeachingCurriculaEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && ($request->user()->teaching_curricula_enabled ?? true), 403, 'Curricula sind ausgeschaltet.');

        $course = $request->route('course');
        $courseDate = $request->route('course_date');
        $material = $request->route('material');
        $attachment = $request->route('attachment');

        if ($courseDate instanceof TeachingCourseDate) {
            $course = $courseDate->teachingCourse;
        } elseif ($material instanceof TeachingCourseDateMaterial) {
            $course = $material->courseDate?->teachingCourse;
        } elseif ($attachment instanceof TeachingCourseDateMaterialAttachment) {
            $course = $attachment->material?->courseDate?->teachingCourse;
        }

        if ($course instanceof TeachingCourse) {
            abort_unless($course->user?->teaching_curricula_enabled ?? true, 403, 'Curricula sind ausgeschaltet.');
        }

        return $next($request);
    }
}
