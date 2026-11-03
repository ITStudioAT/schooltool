<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\ParentStudentAccessService;
use App\Services\TeachingWorkStudentDistribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseWorkTaskController extends Controller
{
    public function index(int $courseId, ParentStudentAccessService $access, TeachingWorkStudentDistribution $distribution): JsonResponse
    {
        $student = $access->currentStudent();
        abort_unless($student, 403);
        $course = $this->authorizedCourse($courseId, $student);
        $tasks = $course->teachingCourseWorks()->orderByDesc('date_for_all_groups')->get()
            ->filter(fn (TeachingCourseWork $work): bool => $distribution->visibleTo($work, $student))
            ->map(fn (TeachingCourseWork $work): array => $distribution->forStudent($work->setRelation('teachingCourse', $course), $student))->values();

        return response()->json(['tasks' => $tasks])->header('Cache-Control', 'private, no-store');
    }

    public function download(int $courseId, TeachingCourseWork $course_work, string $fileId, ParentStudentAccessService $access, TeachingWorkStudentDistribution $distribution): StreamedResponse
    {
        $student = $access->currentStudent();
        abort_unless($student, 403);
        $this->authorizedCourse($courseId, $student);
        abort_unless((int) $course_work->teaching_course_id === $courseId && $distribution->visibleTo($course_work, $student), 404);
        $file = collect($course_work->status['student_task_files'] ?? [])->firstWhere('id', $fileId);
        abort_unless($file && in_array($fileId, $course_work->status['student_distribution']['file_ids'] ?? [], true)
            && ($file['storage_disk'] ?? null) === 'local' && Storage::disk('local')->exists($file['file_path']), 404);

        $stream = Storage::disk('local')->readStream($file['file_path']);
        abort_unless(is_resource($stream), 404);

        return response()->streamDownload(function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === false) {
                        throw new RuntimeException('Das Aufgaben-PDF konnte nicht gelesen werden.');
                    }
                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        }, $file['name'], ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizedCourse(int $courseId, User $student): TeachingCourse
    {
        $yearId = SchoolTool::where('school_id', $student->school_id)->value('active_schoolyear_id') ?? $student->schoolyear_id;
        $course = TeachingCourse::query()->where('school_id', $student->school_id)->where('schoolyear_id', $yearId)->findOrFail($courseId);
        abort_unless($course->teachingCourseStudents()->where('user_id', $student->id)->whereNull('canceled_at')->exists(), 403);

        return $course;
    }
}
