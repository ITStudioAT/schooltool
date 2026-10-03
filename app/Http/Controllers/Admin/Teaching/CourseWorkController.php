<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingCourseStudentEntryService;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingCourseWorkService;
use App\Services\TeachingWorkMarkdownImport;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CourseWorkController extends Controller
{
    public function importEvaluations(Request $request, TeachingCourseWork $course_work, TeachingWorkMarkdownImport $importer): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate([
            'reports' => ['required', 'string', 'max:1048576'],
            'pdfs' => ['sometimes', 'array', 'max:20'],
            'pdfs.*' => ['file', 'extensions:pdf', 'mimetypes:application/pdf', 'max:4096'],
            'apply' => ['sometimes', 'boolean'], 'hash' => ['required_if:apply,1', 'nullable', 'string', 'size:64'],
        ]);
        $documents = json_decode($data['reports'], true);
        if (! is_array($documents) || count($documents) > 100) {
            throw ValidationException::withMessages(['reports' => 'Ungültige oder zu große Berichtsdateiauswahl.']);
        }
        $files = [];
        foreach ($documents as $document) {
            if (! is_array($document) || ! is_string($document['name'] ?? null) || ! is_string($document['text'] ?? null) ||
                basename(str_replace('\\', '/', $document['name'])) !== $document['name'] || strtolower(pathinfo($document['name'], PATHINFO_EXTENSION)) !== 'md' ||
                strlen($document['text']) > 262144 || isset($files[$document['name']])) {
                throw ValidationException::withMessages(['reports' => 'Ungültige oder doppelte Markdown-Datei.']);
            }
            $files[$document['name']] = $document['text'];
        }
        $report = $importer->parse($files);
        $uploads = $request->file('pdfs', []);
        abort_if(array_sum(array_map(fn ($file): int => $file->getSize(), $uploads)) > 6 * 1024 * 1024, 422, 'PDF-Auswahl überschreitet 6 MB.');
        $pdfs = array_map(fn ($file): array => ['name' => basename(str_replace('\\', '/', $file->getClientOriginalName())), 'sha256' => hash_file('sha256', $file->getRealPath())], $uploads);
        $createdPaths = [];
        try {
            $result = DB::transaction(function () use ($course_work, $actor, $importer, $report, $pdfs, $uploads, $data, &$createdPaths): array {
                $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
                $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
                $preview = $importer->preview($work, $this->teachingCourseActor($actor, $work->teachingCourse), $report, $pdfs);
                if (! ($data['apply'] ?? false)) {
                    return ['preview' => $preview];
                }
                abort_unless($preview['can_import'], 422, 'Keine eindeutigen importierbaren Daten.');
                abort_unless(hash_equals($preview['hash'], $data['hash'] ?? ''), 409, 'Arbeit oder Auswertung wurde geändert. Bitte Vorschau erneut laden.');
                $importer->apply($work, $preview);
                $status = $work->status ?? [];
                $attachments = $status['evaluation_pdfs'] ?? [];
                $targets = [['student_id' => null, 'pdf' => $preview['pdf']]];
                foreach ($preview['rows'] as $row) {
                    if ($row['student_id']) {
                        $targets[] = ['student_id' => $row['student_id'], 'pdf' => $row['pdf']];
                    }
                }
                foreach ($targets as $target) {
                    $pdf = $target['pdf'];
                    if (! $pdf || collect($attachments)->contains(fn (array $attachment): bool => $attachment['sha256'] === $pdf['sha256'] && $attachment['student_id'] === $target['student_id'])) {
                        continue;
                    }
                    $directory = "teaching/work_evaluations/{$work->teachingCourse->school_id}/{$work->id}";
                    $path = $directory.'/'.$pdf['sha256'].'.pdf';
                    if (! Storage::disk('local')->exists($path)) {
                        $createdPaths[] = $path;
                        $index = array_search($pdf, $pdfs, true);
                        if ($uploads[$index]->storeAs($directory, $pdf['sha256'].'.pdf', 'local') !== $path) {
                            throw ValidationException::withMessages(['pdfs' => 'Auswertungs-PDF konnte nicht gespeichert werden.']);
                        }
                    }
                    $attachments = array_values(array_filter($attachments, fn (array $attachment): bool => ($attachment['origin'] ?? null) !== 'evaluation_import' || $attachment['student_id'] !== $target['student_id']));
                    $attachments[] = $pdf + ['student_id' => $target['student_id'], 'file_path' => $path, 'storage_disk' => 'local', 'origin' => 'evaluation_import'];
                }
                $status['evaluation_pdfs'] = $attachments;
                $work->status = $status;
                $work->save();

                return ['data' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work->fresh())];
            });
        } catch (Throwable $exception) {
            if ($createdPaths !== []) {
                Storage::disk('local')->delete($createdPaths);
            }
            throw $exception;
        }

        return response()->json($result);
    }

    public function downloadEvaluation(Request $request, TeachingCourseWork $course_work, string $sha256): StreamedResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);

        return app(TeachingWorkMarkdownImport::class)->streamPdf($course_work, $sha256, inline: $request->boolean('inline'));
    }

    public function index(Request $request, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $courseId = $request->query('course_id');

        if (! $courseId) {
            return response()->json(['data' => []]);
        }

        $course = TeachingCourse::findOrFail($courseId);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $works = $course->teachingCourseWorks()
            ->with('teachingCourseWorkGroupStudents')
            ->orderBy('date_for_all_groups', 'desc')
            ->get();

        return response()->json(['data' => $works->map(fn (TeachingCourseWork $work): array => $entrySyncService->serializeWork($work))->values()]);
    }

    public function store(
        Request $request,
        TeachingCourseWorkService $workService,
        TeachingCourseStudentEntryService $entryService,
        TeachingCourseWorkEntrySyncService $entrySyncService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = TeachingCourse::findOrFail($request->input('teaching_course_id'));
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $allowedTypes = $entryService->allowedGradingTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $gradeRules = $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type'));
        $maximumPlus = $this->validatedMaximumPlus($request, $course, $this->teachingCourseActor($auth_user, $course), $entryService);
        $this->appendMaximumPlusGradeRule($gradeRules, $maximumPlus);
        $validated = $request->validate($this->workValidationRules($typeRules, true, $course, $gradeRules));
        if (isset($validated['status'])) {
            unset($validated['status']['evaluation_pdfs']);
        }
        $validated['maximum_plus'] = $maximumPlus;
        $validated['finish_until_date'] ??= $validated['date_for_all_groups'] ?? null;

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id);

        $work = TeachingCourseWork::create($validated);
        $entrySyncService->syncWork($work);

        return response()->json(['data' => $entrySyncService->serializeWork($work->fresh('teachingCourseWorkGroupStudents'))], 201);
    }

    public function show(TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        return response()->json(['data' => $entrySyncService->serializeWork($course_work->load('teachingCourseWorkGroupStudents'))]);
    }

    public function update(
        Request $request,
        TeachingCourseWork $course_work,
        TeachingCourseWorkService $workService,
        TeachingCourseStudentEntryService $entryService,
        TeachingCourseWorkEntrySyncService $entrySyncService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $allowedTypes = $entryService->allowedGradingTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $gradeRules = $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type', $course_work->type));
        $maximumPlus = $this->validatedMaximumPlus($request, $course, $this->teachingCourseActor($auth_user, $course), $entryService, $course_work);
        $this->appendMaximumPlusGradeRule($gradeRules, $maximumPlus);
        $validated = $request->validate($this->workValidationRules($typeRules, false, $course, $gradeRules));
        if (array_key_exists('status', $validated)) {
            $validated['status'] ??= [];
            $validated['status']['evaluation_pdfs'] = $course_work->status['evaluation_pdfs'] ?? [];
        }
        $validated['maximum_plus'] = $maximumPlus;

        if (! array_key_exists('groups', $validated)) {
            Validator::make(['groups' => $course_work->groups], [
                'groups.*.grade' => $gradeRules,
                'groups.*.grades.*.grade' => $gradeRules,
            ])->validate();
            $validated['groups'] = $course_work->groups;
        }

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id, $course_work->is_group_work);

        $course_work->update($validated);
        $entrySyncService->syncWork($course_work->fresh());

        return response()->json(['data' => $entrySyncService->serializeWork($course_work->fresh('teachingCourseWorkGroupStudents'))]);
    }

    public function destroy(TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $entrySyncService->deleteForWork($course_work);
        $course_work->delete();

        return response()->json(null, 204);
    }

    /**
     * Return the shared validation rules for store/update of a course work.
     */
    private function workValidationRules(array $typeRules, bool $isStore, TeachingCourse $course, array $gradeRules): array
    {
        $maximumGroupSize = $course->teachingCourseStudents()
            ->whereNull('canceled_at')
            ->count();

        $rules = [
            'type' => $typeRules,
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1024',
            'is_group_work' => 'sometimes|boolean',
            'group_size' => "nullable|integer|min:2|max:{$maximumGroupSize}",
            'is_random_groups' => 'sometimes|boolean',
            'date_for_all_groups' => 'nullable|date',
            'finish_until_date' => 'nullable|date',
            'groups' => 'nullable|array',
            'groups.*.student_ids' => 'nullable|array',
            'groups.*.student_ids.*' => 'integer',
            'groups.*.date' => 'nullable|date',
            'groups.*.comment' => 'nullable|string|max:1024',
            'groups.*.grade' => $gradeRules,
            'groups.*.grades' => 'nullable|array',
            'groups.*.grades.*.student_id' => 'required|integer',
            'groups.*.grades.*.grade' => $gradeRules,
            'groups.*.points' => 'nullable|array',
            'groups.*.points.*.student_id' => 'required|integer',
            'groups.*.points.*.points' => 'nullable|numeric',
            'groups.*.comments' => 'nullable|array',
            'groups.*.comments.*.student_id' => 'required|integer',
            'groups.*.comments.*.comment' => 'nullable|string|max:1024',
            'groups.*.name' => 'nullable|string|max:255',
            'status' => 'nullable|array',
        ];

        if ($isStore) {
            $rules['teaching_course_id'] = 'required|integer|exists:teaching_courses,id';
        }

        return $rules;
    }

    private function validatedMaximumPlus(Request $request, TeachingCourse $course, User $actor, TeachingCourseStudentEntryService $entryService, ?TeachingCourseWork $work = null): ?int
    {
        $definition = $entryService->entryDefinitionsForCourse($actor, $course)
            ->firstWhere('short_name', $request->input('type', $work?->type));
        if ($definition?->category !== 'Benotung' || ! $definition->has_properties
            || $definition->properties_mode !== 'plus' || ! $definition->allows_maximum_plus) {
            return null;
        }

        $validated = Validator::make([
            'maximum_plus' => $request->input('maximum_plus', $work?->maximum_plus),
        ], [
            'maximum_plus' => ['required', 'integer:strict', 'min:1', 'max:4294967295'],
        ], [
            'maximum_plus.required' => 'Bitte die maximal erreichbare Anzahl an Plus angeben.',
            'maximum_plus.integer' => 'Die maximale Anzahl an Plus muss eine ganze Zahl sein.',
            'maximum_plus.min' => 'Die maximale Anzahl an Plus muss mindestens 1 sein.',
        ])->validate();

        return $validated['maximum_plus'];
    }

    private function appendMaximumPlusGradeRule(array &$gradeRules, ?int $maximumPlus): void
    {
        if ($maximumPlus === null) {
            return;
        }

        $gradeRules[] = function (string $attribute, mixed $value, Closure $fail) use ($maximumPlus): void {
            if (is_string($value) && preg_match('/^\++$/', trim($value)) === 1 && strlen(trim($value)) > $maximumPlus) {
                $fail('Die Anzahl an Plus darf die maximal erreichbare Anzahl nicht überschreiten.');
            }
        };
    }
}
