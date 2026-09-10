<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\CourseDateResource;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseDateMaterialAttachment;
use App\Models\TeachingCurriculumDocument;
use App\Models\User;
use App\Services\TeachingCourseDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseDateController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
        ]);

        $course = TeachingCourse::query()
            ->with('teachingCourseStudents:id,teaching_course_id,user_id,import116_id')
            ->findOrFail($validated['course_id']);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $dates = $course->teachingCourseDates()
            ->with('materials.attachments')
            ->orderBy('date')
            ->get();
        $dates->each(fn (TeachingCourseDate $courseDate) => $courseDate->setRelation('teachingCourse', $course));

        return response()->json(['data' => CourseDateResource::collection($dates)]);
    }

    public function store(Request $request, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
            'from' => 'required|date',
            'until' => 'nullable|date',
            'hours' => 'required|array',
            'hours.*' => 'integer|min:1|max:20',
            'interval' => 'required|integer|in:1,2,3,4',
            'status' => 'nullable|array',
        ]);

        $course = TeachingCourse::findOrFail($validated['course_id']);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $createdDates = $service->createDates(
            $course->id,
            $validated['from'],
            $validated['until'] ?? null,
            $validated['hours'],
            $validated['interval']
        );

        return response()->json(['data' => CourseDateResource::collection($createdDates), 'count' => count($createdDates)], 201);
    }

    public function show(TeachingCourseDate $course_date)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        return response()->json(new CourseDateResource($course_date));
    }

    public function update(Request $request, TeachingCourseDate $course_date, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $validated = $request->validate([
            'date' => 'required|date',
            'hours' => 'nullable|array',
            'content' => 'nullable|string|max:4096',
            'status' => 'nullable|array',
            'status.*' => 'string|in:pruefung,entfaellt',
            'attendance' => 'nullable|array',
            'attendance.*' => 'nullable|boolean',
            'attendance_checked' => 'nullable|boolean',
        ]);

        $service->updateCourseDate($course_date, $validated, $course);

        return response()->json(new CourseDateResource($course_date));
    }

    public function destroy(TeachingCourseDate $course_date, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $service->deleteCourseDate($course_date);

        return response()->json(null, 204);
    }

    public function destroyAll(Request $request, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
        ]);

        $course = TeachingCourse::query()->findOrFail($validated['course_id']);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $deletedCount = $service->deleteCourseDates($course);

        return response()->json(['deleted_count' => $deletedCount]);
    }

    public function adoptCurriculumContent(Request $request, TeachingCourseDate $course_date, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $validated = $request->validate([
            'content' => 'required|string|max:4096',
            'material_card_ids' => 'nullable|array',
            'material_card_ids.*' => 'integer|min:1',
            'material_attachment_ids' => 'nullable|array',
            'material_attachment_ids.*' => 'array',
            'material_attachment_ids.*.*' => 'integer|min:1',
        ]);

        $materialCardIds = array_map('intval', $validated['material_card_ids'] ?? []);
        $materialAttachmentIds = collect($validated['material_attachment_ids'] ?? [])
            ->mapWithKeys(fn (array $attachmentIds, int|string $materialCardId): array => [
                (int) $materialCardId => array_map('intval', $attachmentIds),
            ])
            ->all();

        $copiedMaterials = $service->adoptCurriculumContent($course_date, $validated['content'], $materialCardIds, $materialAttachmentIds);

        $course_date->refresh();

        return response()->json([
            'data' => new CourseDateResource($course_date),
            'adopted_materials' => collect($copiedMaterials)->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'attachments_count' => $m->attachments->count(),
            ])->values(),
        ]);
    }

    public function updateStatus(Request $request, TeachingCourseDate $course_date, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $validated = $request->validate([
            'status' => 'nullable|array',
            'status.*' => 'string|in:pruefung,entfaellt',
            'attendance' => 'nullable|array',
            'attendance.*' => 'nullable|boolean',
            'attendance_checked' => 'nullable|boolean',
            'toggle_student_id' => 'nullable|integer',
            'attendance_state' => 'nullable|boolean',
            'client_toggle_version' => 'nullable|string|max:32',
        ]);

        $service->updateCourseDateStatus($course_date, $validated, $course);

        $course_date->refresh();

        return response()->json(new CourseDateResource($course_date));
    }

    public function toggleAttachmentVisibility(TeachingCourseDateMaterialAttachment $attachment)
    {
        if (! $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $material = $attachment->material;
        $course = $material?->courseDate?->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $this->userHasRole(['admin', 'teaching_admin', 'teacher']));

        $attachment->update(['student_visible' => ! $attachment->student_visible]);

        return response()->json(['student_visible' => $attachment->student_visible]);
    }

    public function setCurriculumFileVisibility(
        Request $request,
        TeachingCourseDate $course_date,
        TeachingCurriculumDocument $file,
        TeachingCourseDateService $service,
    ): JsonResponse {
        $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($authUser && $course_date->teachingCourse, 403, 'Sie haben keine Berechtigung');
        $this->authorizeTeachingCourseAccess($course_date->teachingCourse, $authUser);

        $validated = $request->validate([
            'student_visible' => ['required', 'boolean'],
        ]);
        $service->setCurriculumFileVisibility($course_date, $file, (bool) $validated['student_visible']);

        return response()->json(['data' => new CourseDateResource($course_date->refresh())]);
    }

    public function destroyAdoptedMaterial(TeachingCourseDateMaterial $material, TeachingCourseDateService $service)
    {
        if (! $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $courseDate = $material->courseDate;
        $course = $courseDate?->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $this->userHasRole(['admin', 'teaching_admin', 'teacher']));

        $service->deleteAdoptedMaterial($material);

        return response()->json(null, 204);
    }

    public function previewAdoptedAttachment(TeachingCourseDateMaterialAttachment $attachment)
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->authorizeAdoptedAttachmentAccess($attachment, $authUser);

        return $this->serveAdoptedAttachment($attachment, 'inline');
    }

    public function downloadAdoptedAttachment(TeachingCourseDateMaterialAttachment $attachment)
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $this->authorizeAdoptedAttachmentAccess($attachment, $authUser);

        return $this->serveAdoptedAttachment($attachment, 'attachment');
    }

    private function authorizeAdoptedAttachmentAccess(
        TeachingCourseDateMaterialAttachment $attachment,
        User $authUser
    ): void {
        $attachment->loadMissing('material.courseDate.teachingCourse');
        $course = $attachment->material?->courseDate?->teachingCourse;

        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $authUser);
    }

    private function serveAdoptedAttachment(TeachingCourseDateMaterialAttachment $attachment, string $disposition)
    {
        $path = trim((string) $attachment->file_path);
        if ($path === '') {
            abort(404, 'Datei nicht gefunden');
        }

        $candidates = array_values(array_unique(array_filter([
            (string) config('filesystems.default'),
            's3',
            'local',
        ])));

        $disk = null;
        foreach ($candidates as $diskName) {
            $candidate = Storage::disk($diskName);
            if ($candidate->exists($path)) {
                $disk = $candidate;
                break;
            }
        }

        if (! $disk) {
            abort(404, 'Datei nicht gefunden');
        }

        $name = $this->attachmentNameWithStorageExtension($attachment->name, $path);
        $mime = $attachment->mime_type ?: ($disk->mimeType($path) ?: 'application/octet-stream');

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($name, '"').'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($disposition === 'inline' && $this->isActiveAttachmentContent($name, $mime)) {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'; base-uri 'none'; form-action 'none'";
        }

        return $disk->response($path, $name, $headers);
    }

    private function isActiveAttachmentContent(string $name, string $mimeType): bool
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $normalizedMimeType = strtolower(trim($mimeType));

        return in_array($extension, ['html', 'htm', 'xhtml', 'svg'], true)
            || in_array($normalizedMimeType, ['text/html', 'application/xhtml+xml', 'image/svg+xml'], true);
    }

    private function attachmentNameWithStorageExtension(?string $name, ?string $path): string
    {
        $relativePath = trim((string) $path);
        $displayName = trim((string) ($name ?: basename($relativePath)));
        $displayName = $displayName !== '' ? $displayName : 'Anhang';
        $displayExtension = strtolower((string) pathinfo($displayName, PATHINFO_EXTENSION));
        $pathExtension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));

        if ($displayExtension === '' && preg_match('/^[a-z0-9]{1,10}$/', $pathExtension) === 1) {
            return $displayName.'.'.$pathExtension;
        }

        return $displayName;
    }
}
