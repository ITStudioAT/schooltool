<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Enums\StudentTimetableStudyProgram;
use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\StudentTimetableSubjectImport;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\StudentsTimetables\StudentTimetableSubjectPlanCarryForwardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SubjectOverviewJsonUploadController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'studentstimetables_admin'];

    private const MODERATOR_ROLES = ['super_admin', 'admin', 'studentstimetables_admin', 'studentstimetables_moderator'];

    private const CANONICAL_SUBJECT_NAMES = [
        'LPT' => 'Lern- und Präsentationstechniken',
    ];

    public function __construct(
        private StudentTimetableSubjectPlanCarryForwardService $subjectPlanCarryForwardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::MODERATOR_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeToSchoolImportSchoolyear($authUser);
        $studyProgram = $this->studyProgram($request);

        if ($request->boolean('summary')) {
            $import = StudentTimetableSubjectImport::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->forStudyProgram($studyProgram)
                ->orderByDesc('imported_at')
                ->orderByDesc('id')
                ->first([
                    'id',
                    'study_program',
                    'stored_filename',
                    'original_filename',
                    'file_size',
                    'subjects_total',
                    'subject_rows_total',
                    'semesters_total',
                    'branches_total',
                    'imported_at',
                ]);

            return response()->json([
                'data' => $import ? [$this->subjectImportSummaryPayload($import)] : [],
                'total' => $import ? 1 : 0,
                'active_dataset' => null,
                'study_program' => $studyProgram->value,
            ]);
        }

        $this->importLegacyJsonIfMissing($authUser, $studyProgram);

        $imports = StudentTimetableSubjectImport::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->forStudyProgram($studyProgram)
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentTimetableSubjectImport $import): array => $this->subjectImportPayload($import))
            ->values();

        return response()->json([
            'data' => $imports,
            'total' => $imports->count(),
            'active_dataset' => $this->activeSubjectDatasetMetadata($authUser, $studyProgram),
            'study_program' => $studyProgram->value,
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::MODERATOR_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeSettingsSchoolyear($authUser, $request);
        $studyProgram = $this->studyProgram($request);

        if ($studyProgram === StudentTimetableStudyProgram::Normalstudium) {
            $this->seedEditableDataFromLatestImportIfMissing($authUser, $studyProgram);
        }

        $settingsData = $this->editableSettingsData($authUser, $studyProgram, $request->boolean('subjects_only'));

        $settingsData['previous_schoolyear'] = $this->subjectPlanCarryForwardService->previousSchoolyearSummary(
            (int) $authUser->school_id,
            (int) $authUser->schoolyear_id,
        );

        return response()->json([
            'data' => $settingsData,
        ]);
    }

    public function carryForwardSubjectPlan(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeSettingsSchoolyear($authUser, $request);
        $result = $this->subjectPlanCarryForwardService->carryForwardFromPreviousSchoolyear(
            (int) $authUser->school_id,
            (int) $authUser->schoolyear_id,
        );
        $mappingLabel = $result['mappings_count'] === 1
            ? '1 Zuordnung'
            : "{$result['mappings_count']} Zuordnungen";

        return response()->json([
            'message' => "{$result['subject_rows_count']} Fachzeilen und {$mappingLabel} aus {$result['previous_schoolyear']['name']} übernommen.",
        ]);
    }

    public function updateSubjects(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeSettingsSchoolyear($authUser, $request);
        $studyProgram = $this->studyProgram($request);

        $validated = $request->validate([
            'subjects' => ['array'],
            'subjects.*.semester' => ['nullable', 'integer', 'between:1,20'],
            'subjects.*.branch' => ['nullable', 'string', 'max:80'],
            'subjects.*.json_code' => ['nullable', 'string', 'max:80'],
            'subjects.*.json_subject' => ['nullable', 'string', 'max:80'],
            'subjects.*.name' => ['nullable', 'string', 'max:255'],
            'subjects.*.hours_per_week' => ['nullable', 'numeric', 'between:0,99'],
            'subjects.*.is_active' => ['boolean'],
        ]);

        $subjects = collect($validated['subjects'] ?? [])
            ->map(fn (array $subject, int $index): array => [
                'school_id' => $authUser->school_id,
                'schoolyear_id' => $authUser->schoolyear_id,
                'study_program' => $studyProgram->value,
                'semester' => $subject['semester'] ?? null,
                'branch' => $this->normalizeSubjectBranch($subject['branch'] ?? null),
                'json_code' => $this->emptyToNull($subject['json_code'] ?? null),
                'json_subject' => $this->emptyToNull($subject['json_subject'] ?? null),
                'name' => $this->canonicalSubjectName(
                    $subject['name'] ?? null,
                    $subject['json_code'] ?? null,
                    $subject['json_subject'] ?? null,
                ),
                'hours_per_week' => $subject['hours_per_week'] ?? null,
                'is_active' => (bool) ($subject['is_active'] ?? true),
                'sort_order' => $index,
                'source' => 'manual',
            ])
            ->filter(fn (array $subject): bool => $this->subjectRowHasContent($subject))
            ->values();

        DB::transaction(function () use ($authUser, $studyProgram, $subjects): void {
            StudentTimetableSubjectRow::query()
                ->forStudyProgram($studyProgram)
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->delete();

            $subjects->each(fn (array $subject): StudentTimetableSubjectRow => StudentTimetableSubjectRow::create($subject));
        });

        return response()->json([
            'data' => $this->editableSettingsData($authUser, $studyProgram),
        ]);
    }

    public function updateMappings(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeSettingsSchoolyear($authUser, $request);
        $studyProgram = $this->studyProgram($request);

        $validated = $request->validate([
            'mappings' => ['array'],
            'mappings.*.json_subject' => ['nullable', 'string', 'max:80'],
            'mappings.*.tt_subject' => ['nullable', 'string', 'max:80'],
            'mappings.*.note' => ['nullable', 'string', 'max:255'],
            'mappings.*.is_active' => ['boolean'],
        ]);

        $mappings = collect($validated['mappings'] ?? [])
            ->map(fn (array $mapping): array => [
                'school_id' => $authUser->school_id,
                'schoolyear_id' => $authUser->schoolyear_id,
                'json_subject' => $this->emptyToNull($mapping['json_subject'] ?? null),
                'tt_subject' => $this->emptyToNull($mapping['tt_subject'] ?? null),
                'note' => $this->emptyToNull($mapping['note'] ?? null),
                'is_active' => (bool) ($mapping['is_active'] ?? true),
                'source' => 'manual',
            ])
            ->filter(fn (array $mapping): bool => $mapping['json_subject'] && $mapping['tt_subject'])
            ->unique(fn (array $mapping): string => Str::lower($mapping['json_subject'].'|'.$mapping['tt_subject']))
            ->values();

        DB::transaction(function () use ($authUser, $mappings): void {
            StudentTimetableSubjectMapping::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->delete();

            $mappings->each(fn (array $mapping): StudentTimetableSubjectMapping => StudentTimetableSubjectMapping::create($mapping));
        });

        return response()->json([
            'data' => $this->editableSettingsData($authUser, $studyProgram),
        ]);
    }

    public function upload(Request $request, FileUploadService $fileUploadService): Response
    {
        if (! $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->studyProgram($request);
        $this->ensureJson();

        $id = $fileUploadService->upload($request, 'subject-import');

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, FileUploadService $fileUploadService): Response
    {
        if (! $authUser = $this->userHasRole(self::ADMIN_ROLES)) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $authUser = $this->scopeToSchoolImportSchoolyear($authUser);
        $studyProgram = $this->studyProgram($request);

        $this->ensureJson();

        $uploadPath = $this->storageDirectory($authUser->school_id, $authUser->schoolyear_id, $studyProgram);
        $storedName = $this->storedFilename($request->header('Upload-Name'));

        $result = $fileUploadService->uploadNext(
            $request,
            $uploadPath,
            $storedName,
            profile: 'subject-import',
        );

        if ($result instanceof Response) {
            return $result;
        }

        $storedPath = storage_path("{$uploadPath}/{$result}");

        $this->ensureStoredFileContainsJson("{$uploadPath}/{$result}");
        $this->ensureJsonStudyProgramMatches($storedPath, $studyProgram);
        $analysis = $this->analyzeJsonFile($storedPath, $studyProgram);
        $this->ensureSubjectRowsWereAnalyzed($storedPath, $analysis);
        $originalFilename = $request->header('Upload-Name') ?: $storedName;

        DB::transaction(function () use ($authUser, $studyProgram, $originalFilename, $result, $uploadPath, $storedPath, $analysis): void {
            $this->createSubjectImport(
                $authUser,
                $studyProgram,
                $originalFilename,
                $result,
                "{$uploadPath}/{$result}",
                $storedPath,
                $analysis,
            );
            $this->replaceSubjectRowsFromAnalysis($authUser, $studyProgram, $analysis);
            $this->seedDefaultSubjectMappings(
                $authUser,
                collect($analysis['subject_rows'] ?? [])->pluck('json_subject')->filter()->unique()->values()->all(),
            );
        });

        return response($result, 200)->header('Content-Type', 'text/plain');
    }

    private function ensureJson(): void
    {
        $originalName = request()->header('Upload-Name');
        if (! $originalName) {
            return;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'json') {
            abort(422, 'Nur JSON-Dateien sind erlaubt.');
        }
    }

    private function studyProgram(Request $request): StudentTimetableStudyProgram
    {
        $value = $request->route('studyProgram');

        if ($value === null || $value === '') {
            return StudentTimetableStudyProgram::Normalstudium;
        }

        $studyProgram = is_string($value)
            ? StudentTimetableStudyProgram::tryFrom(Str::lower(trim($value)))
            : null;

        if (! $studyProgram) {
            abort(422, 'Ungültige Studienform.');
        }

        return $studyProgram;
    }

    private function ensureJsonStudyProgramMatches(
        string $storedPath,
        StudentTimetableStudyProgram $studyProgram,
    ): void {
        $contents = file_get_contents($storedPath);
        $data = is_string($contents) ? json_decode($contents, true) : null;
        $declaredValue = is_array($data) ? ($data['study_program'] ?? null) : null;

        if ($declaredValue === null || $declaredValue === '') {
            return;
        }

        $declaredStudyProgram = is_string($declaredValue)
            ? StudentTimetableStudyProgram::tryFrom(Str::lower(trim($declaredValue)))
            : null;

        if ($declaredStudyProgram === $studyProgram) {
            return;
        }

        @unlink($storedPath);
        abort(422, 'Die Studienform der JSON-Datei passt nicht zum gewählten Import.');
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function ensureSubjectRowsWereAnalyzed(string $storedPath, array $analysis): void
    {
        if (count($analysis['subject_rows'] ?? []) > 0) {
            return;
        }

        @unlink($storedPath);
        abort(422, 'Die JSON-Datei enthält keine importierbaren Fächer.');
    }

    private function storedFilename(mixed $originalName): string
    {
        $baseName = is_string($originalName) ? pathinfo($originalName, PATHINFO_FILENAME) : 'faecher-uebersicht';
        $baseName = Str::slug($baseName) ?: 'faecher-uebersicht';
        $timestamp = now()->format('Ymd_His');

        return "{$baseName}_{$timestamp}";
    }

    private function ensureStoredFileContainsJson(string $relativePath): void
    {
        $path = storage_path($relativePath);
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            abort(500, 'Die JSON-Datei konnte nicht gespeichert werden.');
        }

        json_decode($contents);

        if (json_last_error() === JSON_ERROR_NONE) {
            return;
        }

        @unlink($path);

        abort(422, 'Die Datei enthält kein gültiges JSON.');
    }

    /**
     * @return array{study_program: string, subjects_total: int, semesters: list<array<string, mixed>>, branches: list<array<string, mixed>>, subject_rows: list<array<string, mixed>>}
     */
    private function analyzeJsonFile(
        string $path,
        StudentTimetableStudyProgram $studyProgram = StudentTimetableStudyProgram::Normalstudium,
    ): array {
        $contents = file_get_contents($path);
        $data = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($data)) {
            return [
                'study_program' => $studyProgram->value,
                'subjects_total' => 0,
                'semesters' => [],
                'branches' => [],
                'subject_rows' => [],
            ];
        }

        $subjectsBySemester = [];
        $commonSubjectsBySemester = [];
        $subjectsByBranch = [];
        $subjectRows = [];
        $branchLabels = $this->branchLabels($data);
        $courseAbbreviations = $this->courseAbbreviations($data);
        $this->collectSubjects($data, $subjectsBySemester, $commonSubjectsBySemester, $subjectsByBranch, $subjectRows, $branchLabels, $courseAbbreviations);

        $semesters = collect($subjectsBySemester)
            ->map(fn (array $subjects, string $semesterKey): array => $this->semesterAnalysis(
                $semesterKey,
                $subjects,
                $this->semesterBranchVariants(
                    $semesterKey,
                    $commonSubjectsBySemester[$semesterKey] ?? [],
                    $subjectsByBranch,
                    $branchLabels,
                ),
            ))
            ->sortBy(fn (array $semester): int|string => $this->semesterSortValue($semester['key']))
            ->values()
            ->all();

        return [
            'study_program' => $studyProgram->value,
            'subjects_total' => collect($semesters)
                ->flatMap(fn (array $semester): array => $semester['subjects'])
                ->pluck('name')
                ->unique()
                ->count(),
            'semesters' => $semesters,
            'branches' => $this->branchAnalysis($subjectsByBranch, $branchLabels),
            'subject_rows' => collect($subjectRows)
                ->sortBy([
                    ['semester', 'asc'],
                    ['branch', 'asc'],
                    ['json_code', 'asc'],
                    ['name', 'asc'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<string, array<string, array{name: string, short_name: ?string, count: int}>>  $subjectsBySemester
     * @param  array<string, array<string, array{name: string, short_name: ?string, count: int}>>  $commonSubjectsBySemester
     * @param  array<string, array<string, array<string, array{name: string, short_name: ?string, count: int}>>>  $subjectsByBranch
     * @param  array<string, array<string, mixed>>  $subjectRows
     * @param  array<string, string>  $branchLabels
     * @param  array<string, string>  $courseAbbreviations
     */
    private function collectSubjects(
        array $node,
        array &$subjectsBySemester,
        array &$commonSubjectsBySemester,
        array &$subjectsByBranch,
        array &$subjectRows,
        array $branchLabels,
        array $courseAbbreviations,
        ?string $semester = null,
        ?string $branch = null,
        ?string $contextKey = null,
    ): void {
        $semester = $this->semesterFromNode($node) ?? $this->semesterFromKey($contextKey) ?? $semester;
        $branch = $this->branchFromKey($contextKey, $branchLabels) ?? $branch;
        $subjects = $this->subjectsFromNode($node, $contextKey, $semester, $courseAbbreviations);

        foreach ($subjects as $subject) {
            $semesterKey = $semester ?: 'unknown';
            $subjectKey = Str::lower($subject['name']);

            $subjectsBySemester[$semesterKey] ??= [];
            $this->addSubject($subjectsBySemester[$semesterKey], $subjectKey, $subject);

            if ($branch) {
                $subjectsByBranch[$branch] ??= [];
                $subjectsByBranch[$branch][$semesterKey] ??= [];
                $this->addSubject($subjectsByBranch[$branch][$semesterKey], $subjectKey, $subject);
            } else {
                $commonSubjectsBySemester[$semesterKey] ??= [];
                $this->addSubject($commonSubjectsBySemester[$semesterKey], $subjectKey, $subject);
            }

            $subjectRow = $this->subjectRowFromNode($node, $subject, $semesterKey, $branch);
            $subjectRows[$this->subjectRowSignature($subjectRow)] = $subjectRow;
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $this->collectSubjects(
                    $value,
                    $subjectsBySemester,
                    $commonSubjectsBySemester,
                    $subjectsByBranch,
                    $subjectRows,
                    $branchLabels,
                    $courseAbbreviations,
                    $semester,
                    $branch,
                    is_string($key) ? $key : $contextKey,
                );
            }
        }
    }

    /**
     * @param  array<mixed>  $node
     * @param  array{name: string, short_name: ?string, json_subject: ?string, hours_per_week?: ?float}  $subject
     * @return array<string, mixed>
     */
    private function subjectRowFromNode(array $node, array $subject, string $semesterKey, ?string $branch): array
    {
        return [
            'semester' => $this->semesterNumber($semesterKey),
            'branch' => $this->normalizeSubjectBranch($branch),
            'json_code' => $subject['short_name'] ?: $subject['name'],
            'json_subject' => $subject['json_subject'] ?: $subject['name'],
            'name' => $this->canonicalSubjectName($subject['name'], $subject['short_name'], $subject['json_subject']),
            'hours_per_week' => $subject['hours_per_week'] ?? $this->firstNumericValue($node, ['hours_per_week', 'hours', 'stunden', 'wochenstunden']),
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function subjectRowSignature(array $row): string
    {
        return Str::lower(implode('|', [
            $row['semester'] ?? '',
            $row['branch'] ?? '',
            $row['json_code'] ?? '',
            $row['json_subject'] ?? '',
            $row['name'] ?? '',
            $row['hours_per_week'] ?? '',
        ]));
    }

    /**
     * @param  array<string, array{name: string, short_name: ?string, count: int}>  $target
     * @param  array{name: string, short_name: ?string}  $subject
     */
    private function addSubject(array &$target, string $subjectKey, array $subject): void
    {
        if (! isset($target[$subjectKey])) {
            $target[$subjectKey] = [
                'name' => $subject['name'],
                'short_name' => $subject['short_name'],
                'count' => 0,
            ];
        }

        $target[$subjectKey]['count']++;
    }

    /**
     * @param  array{name: string, short_name: ?string, count: int}[]  $subjects
     * @param  list<array<string, mixed>>  $branchVariants
     * @return array<string, mixed>
     */
    private function semesterAnalysis(string $semesterKey, array $subjects, array $branchVariants = []): array
    {
        $items = collect($subjects)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'key' => $semesterKey,
            'label' => $this->semesterLabel($semesterKey),
            'subjects_count' => count($items),
            'subjects' => $items,
            'branch_variants' => $branchVariants,
        ];
    }

    /**
     * @param  array<string, array{name: string, short_name: ?string, count: int}>  $commonSubjects
     * @param  array<string, array<string, array<string, array{name: string, short_name: ?string, count: int}>>>  $subjectsByBranch
     * @param  array<string, string>  $branchLabels
     * @return list<array<string, mixed>>
     */
    private function semesterBranchVariants(
        string $semesterKey,
        array $commonSubjects,
        array $subjectsByBranch,
        array $branchLabels,
    ): array {
        $variants = collect($branchLabels)
            ->map(function (string $branchLabel, string $branchKey) use ($semesterKey, $commonSubjects, $subjectsByBranch): array {
                $subjects = $this->mergedSubjects($commonSubjects, $subjectsByBranch[$branchKey][$semesterKey] ?? []);

                return [
                    'key' => $branchKey,
                    'label' => $branchLabel,
                    'subjects_count' => count($subjects),
                    'subjects' => $subjects,
                ];
            })
            ->values()
            ->all();

        if (count($variants) > 1 && $this->branchVariantsHaveSameSubjects($variants)) {
            $subjects = $variants[0]['subjects'];

            return [[
                'key' => 'common',
                'label' => '',
                'subjects_count' => count($subjects),
                'subjects' => $subjects,
                'common_subjects' => $subjects,
                'different_subjects' => [],
            ]];
        }

        return $this->withCommonSubjectVariant($variants);
    }

    /**
     * @param  list<array{key: string, label: string, subjects_count: int, subjects: list<array{name: string, short_name: ?string, count: int}>}>  $variants
     */
    private function branchVariantsHaveSameSubjects(array $variants): bool
    {
        $firstSignature = $this->subjectListSignature($variants[0]['subjects'] ?? []);

        foreach (array_slice($variants, 1) as $variant) {
            if ($this->subjectListSignature($variant['subjects']) !== $firstSignature) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{key: string, label: string, subjects_count: int, subjects: list<array{name: string, short_name: ?string, count: int}>}>  $variants
     * @return list<array<string, mixed>>
     */
    private function withCommonSubjectVariant(array $variants): array
    {
        $subjectOccurrences = [];

        foreach ($variants as $variant) {
            foreach ($variant['subjects'] as $subject) {
                $subjectOccurrences[$this->subjectSignature($subject)] ??= 0;
                $subjectOccurrences[$this->subjectSignature($subject)]++;
            }
        }

        $variantCount = count($variants);
        $commonSubjects = collect($variants[0]['subjects'] ?? [])
            ->filter(fn (array $subject): bool => $subjectOccurrences[$this->subjectSignature($subject)] === $variantCount)
            ->values()
            ->all();

        $branchVariants = collect($variants)
            ->map(function (array $variant) use ($subjectOccurrences, $variantCount): array {
                $differentSubjects = collect($variant['subjects'])
                    ->filter(fn (array $subject): bool => $subjectOccurrences[$this->subjectSignature($subject)] !== $variantCount)
                    ->values()
                    ->all();

                return [
                    ...$variant,
                    'subjects_count' => count($differentSubjects),
                    'subjects' => $differentSubjects,
                    'common_subjects' => [],
                    'different_subjects' => $differentSubjects,
                ];
            })
            ->filter(fn (array $variant): bool => $variant['subjects_count'] > 0)
            ->values()
            ->all();

        if (! $commonSubjects) {
            return $branchVariants;
        }

        return [
            [
                'key' => 'common',
                'label' => '',
                'subjects_count' => count($commonSubjects),
                'subjects' => $commonSubjects,
                'common_subjects' => $commonSubjects,
                'different_subjects' => [],
            ],
            ...$branchVariants,
        ];
    }

    /**
     * @param  list<array{name: string, short_name: ?string, count: int}>  $subjects
     * @return list<string>
     */
    private function subjectListSignature(array $subjects): array
    {
        return collect($subjects)
            ->map(fn (array $subject): string => $this->subjectSignature($subject))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, short_name: ?string, count: int}  $subject
     */
    private function subjectSignature(array $subject): string
    {
        return Str::lower(($subject['short_name'] ?? '').'|'.$subject['name']);
    }

    /**
     * @param  array<string, array{name: string, short_name: ?string, count: int}>  $baseSubjects
     * @param  array<string, array{name: string, short_name: ?string, count: int}>  $additionalSubjects
     * @return list<array{name: string, short_name: ?string, count: int}>
     */
    private function mergedSubjects(array $baseSubjects, array $additionalSubjects): array
    {
        $subjects = $baseSubjects;

        foreach ($additionalSubjects as $subjectKey => $subject) {
            if (! isset($subjects[$subjectKey])) {
                $subjects[$subjectKey] = $subject;

                continue;
            }

            $subjects[$subjectKey]['count'] += $subject['count'];
        }

        return collect($subjects)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, array<string, array{name: string, short_name: ?string, count: int}>>>  $subjectsByBranch
     * @param  array<string, string>  $branchLabels
     * @return list<array{key: string, label: string, subjects_total: int, semesters: list<array{key: string, label: string, subjects_count: int, subjects: list<array{name: string, short_name: ?string, count: int}>}>}>
     */
    private function branchAnalysis(array $subjectsByBranch, array $branchLabels): array
    {
        return collect($branchLabels)
            ->map(function (string $branchLabel, string $branchKey) use ($subjectsByBranch): array {
                $semesters = collect($subjectsByBranch[$branchKey] ?? [])
                    ->map(fn (array $subjects, string $semesterKey): array => $this->semesterAnalysis($semesterKey, $subjects))
                    ->sortBy(fn (array $semester): int|string => $this->semesterSortValue($semester['key']))
                    ->values()
                    ->all();

                return [
                    'key' => $branchKey,
                    'label' => $branchLabel,
                    'subjects_total' => collect($semesters)
                        ->flatMap(fn (array $semester): array => $semester['subjects'])
                        ->pluck('name')
                        ->unique()
                        ->count(),
                    'semesters' => $semesters,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<mixed>  $node
     */
    private function semesterFromNode(array $node): ?string
    {
        foreach (['semester', 'sem', 'halbjahr'] as $key) {
            if (! array_key_exists($key, $node)) {
                continue;
            }

            $semester = $this->normalizeSemester($node[$key]);
            if ($semester) {
                return $semester;
            }
        }

        return null;
    }

    private function semesterFromKey(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        return $this->normalizeSemester($key);
    }

    private function normalizeSemester(mixed $value): ?string
    {
        $normalized = Str::lower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(?:^|[^0-9])([1-9][0-9]?)(?:[^0-9]|$)/', $normalized, $matches) && str_contains($normalized, 'sem')) {
            return "semester_{$matches[1]}";
        }

        if (preg_match('/^[1-9][0-9]?$/', $normalized)) {
            return "semester_{$normalized}";
        }

        return null;
    }

    /**
     * @param  array<mixed>  $node
     * @return list<array{name: string, short_name: ?string, json_subject: ?string, hours_per_week: ?float}>
     */
    private function subjectsFromNode(array $node, ?string $contextKey, ?string $semester, array $courseAbbreviations): array
    {
        $rawSubject = $this->firstStringValue($node, ['fach', 'subject']);
        $explicitName = $this->firstStringValue($node, ['subject_name', 'name', 'long_name', 'title', 'bezeichnung']);
        $shortName = $this->firstStringValue($node, ['short_name', 'shortName', 'kurzname', 'kuerzel', 'code']);
        $jsonSubject = $rawSubject ?: $this->subjectCodeWithoutModule($shortName);
        $abbreviationKey = $rawSubject ?: $this->subjectCodeWithoutModule($shortName);
        $subjectCodes = $this->subjectCodes($shortName, $abbreviationKey, $courseAbbreviations);
        $hoursPerWeek = $this->subjectHoursPerCode($node, $subjectCodes);

        if (! $semester && ! $this->isSubjectContext($contextKey) && ! $shortName) {
            return [];
        }

        return collect($subjectCodes)
            ->map(function (?string $subjectCode) use ($rawSubject, $explicitName, $jsonSubject, $abbreviationKey, $courseAbbreviations, $hoursPerWeek): ?array {
                $name = $abbreviationKey ? ($courseAbbreviations[$abbreviationKey] ?? null) : null;
                $name = $name ? $this->subjectNameWithModule($name, $abbreviationKey, $subjectCode) : null;
                $name ??= $explicitName ? $this->subjectNameWithModule($explicitName, $abbreviationKey, $subjectCode) : null;
                $name ??= $rawSubject;

                if (! $name && $subjectCode) {
                    $name = $subjectCode;
                }

                if (! $name) {
                    return null;
                }

                return [
                    'name' => $name,
                    'short_name' => $subjectCode && $subjectCode !== $name ? $subjectCode : null,
                    'json_subject' => $jsonSubject,
                    'hours_per_week' => $hoursPerWeek,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, string>
     */
    private function courseAbbreviations(array $data): array
    {
        $abbreviations = $data['course_abbreviations'] ?? [];

        if (! is_array($abbreviations)) {
            return [];
        }

        return collect($abbreviations)
            ->filter(fn (mixed $value, mixed $key): bool => is_scalar($key) && is_scalar($value))
            ->mapWithKeys(fn (mixed $value, mixed $key): array => [trim((string) $key) => trim((string) $value)])
            ->filter(fn (string $value, string $key): bool => $key !== '' && $value !== '')
            ->all();
    }

    private function subjectCodeWithoutModule(?string $code): ?string
    {
        $code = $this->emptyToNull($code);

        if (! $code) {
            return null;
        }

        return preg_replace('/\d+$/', '', $code) ?: $code;
    }

    /**
     * @return list<?string>
     */
    private function subjectCodes(?string $code, ?string $abbreviationKey, array $courseAbbreviations): array
    {
        if (! $code) {
            return [null];
        }

        if (! $abbreviationKey || ! array_key_exists($abbreviationKey, $courseAbbreviations)) {
            return [$code];
        }

        $explicitCodes = $this->explicitSubjectCodes($code, $abbreviationKey);
        if ($explicitCodes !== []) {
            return $explicitCodes;
        }

        if (preg_match('/^'.preg_quote($abbreviationKey, '/').'([1-9]{2,})$/u', $code, $matches) !== 1) {
            return [$code];
        }

        return collect(str_split($matches[1]))
            ->map(fn (string $moduleNumber): string => "{$abbreviationKey}{$moduleNumber}")
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function explicitSubjectCodes(string $code, string $abbreviationKey): array
    {
        if (! str_contains($code, '/')) {
            return [];
        }

        $codes = collect(explode('/', $code))
            ->map(fn (string $part): string => trim($part))
            ->filter()
            ->values();

        if ($codes->count() < 2) {
            return [];
        }

        $matchesAbbreviation = $codes->every(
            fn (string $part): bool => preg_match('/^'.preg_quote($abbreviationKey, '/').'\d+$/u', $part) === 1,
        );

        return $matchesAbbreviation ? $codes->all() : [];
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<?string>  $subjectCodes
     */
    private function subjectHoursPerCode(array $node, array $subjectCodes): ?float
    {
        $hours = $this->firstNumericValue($node, ['hours_per_week', 'hours', 'stunden', 'wochenstunden']);

        if ($hours === null) {
            return null;
        }

        $subjectCodeCount = collect($subjectCodes)
            ->filter(fn (?string $subjectCode): bool => $subjectCode !== null)
            ->count();

        if ($subjectCodeCount <= 1) {
            return $hours;
        }

        return $hours / $subjectCodeCount;
    }

    private function subjectNameWithModule(string $name, ?string $abbreviationKey, ?string $code): string
    {
        if (! $abbreviationKey || ! $code) {
            return $name;
        }

        if (! preg_match('/^'.preg_quote($abbreviationKey, '/').'(\d+)$/u', $code, $matches)) {
            return $name;
        }

        return "{$name} {$matches[1]}";
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $keys
     */
    private function firstStringValue(array $node, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $node) || ! is_scalar($node[$key])) {
                continue;
            }

            $value = trim((string) $node[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $node
     * @param  list<string>  $keys
     */
    private function firstNumericValue(array $node, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $node) || ! is_scalar($node[$key])) {
                continue;
            }

            $value = str_replace(',', '.', trim((string) $node[$key]));
            if ($value !== '' && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function isSubjectContext(?string $key): bool
    {
        if (! $key) {
            return false;
        }

        $normalized = Str::lower($key);

        return str_contains($normalized, 'subject')
            || str_contains($normalized, 'fach')
            || str_contains($normalized, 'faecher')
            || str_contains($normalized, 'fächer');
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, string>
     */
    private function branchLabels(array $data): array
    {
        $labels = [];
        $branches = $data['branches'] ?? [];

        if (is_array($branches)) {
            foreach ($branches as $key => $branch) {
                if (! is_string($key)) {
                    continue;
                }

                $branchKey = $this->normalizeBranchKey($key);
                $label = is_array($branch) && is_scalar($branch['label'] ?? null)
                    ? trim((string) $branch['label'])
                    : '';

                $labels[$branchKey] = $label !== '' ? $label : $this->branchFallbackLabel($branchKey);
            }
        }

        foreach (['wirtschaftskundlich', 'gymnasial'] as $branchKey) {
            $labels[$branchKey] ??= $this->branchFallbackLabel($branchKey);
        }

        return $labels;
    }

    /**
     * @param  array<string, string>  $branchLabels
     */
    private function branchFromKey(?string $key, array $branchLabels): ?string
    {
        if (! $key) {
            return null;
        }

        $normalized = $this->normalizeBranchKey($key);

        return array_key_exists($normalized, $branchLabels) ? $normalized : null;
    }

    private function normalizeBranchKey(string $key): string
    {
        $normalized = Str::lower(trim($key));
        $normalized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: $normalized;

        return trim($normalized, '_');
    }

    private function branchFallbackLabel(string $branchKey): string
    {
        return match ($branchKey) {
            'wirtschaftskundlich' => 'Wirtschaftskundlicher Zweig',
            'gymnasial' => 'Gymnasialer Zweig',
            default => Str::headline(str_replace('_', ' ', $branchKey)),
        };
    }

    private function semesterLabel(string $semesterKey): string
    {
        $semesterNumber = $this->semesterNumber($semesterKey);

        return $semesterNumber ? "{$semesterNumber}. Semester" : 'Ohne Semester';
    }

    private function semesterSortValue(string $semesterKey): int|string
    {
        return $this->semesterNumber($semesterKey) ?? 99;
    }

    private function semesterNumber(string $semesterKey): ?int
    {
        if (! preg_match('/^semester_([1-9][0-9]?)$/', $semesterKey, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function storageDirectory(
        int|string|null $schoolId,
        int|string|null $schoolyearId,
        StudentTimetableStudyProgram $studyProgram = StudentTimetableStudyProgram::Normalstudium,
    ): string {
        $directory = "app/private/{$schoolId}/student-timetable-subjects/{$schoolyearId}";

        return $studyProgram === StudentTimetableStudyProgram::Normalstudium
            ? $directory
            : "{$directory}/{$studyProgram->value}";
    }

    private function scopeToSchoolImportSchoolyear(User $authUser): User
    {
        $schoolyearId = SchoolTool::query()
            ->where('school_id', $authUser->school_id)
            ->value('active_schoolyear_id') ?: $authUser->schoolyear_id;

        if (! $schoolyearId) {
            abort(422, 'Kein aktives Schuljahr gefunden.');
        }

        $authUser->schoolyear_id = (int) $schoolyearId;

        return $authUser;
    }

    private function scopeSettingsSchoolyear(User $authUser, Request $request): User
    {
        if ($request->string('schoolyear_scope')->toString() !== 'personal') {
            return $this->scopeToSchoolImportSchoolyear($authUser);
        }

        if (! $authUser->schoolyear_id) {
            abort(422, 'Kein persönliches Schuljahr gefunden.');
        }

        return $authUser;
    }

    /**
     * @return Collection<int, string>
     */
    private function jsonFiles(mixed $authUser): Collection
    {
        $directory = storage_path($this->storageDirectory($authUser->school_id, $authUser->schoolyear_id));

        return collect(File::glob("{$directory}/*.json") ?: [])
            ->filter(fn (string $path): bool => is_file($path))
            ->sortByDesc(fn (string $path): int|false => filemtime($path))
            ->values();
    }

    private function importLegacyJsonIfMissing(
        mixed $authUser,
        StudentTimetableStudyProgram $studyProgram,
    ): void {
        if ($studyProgram !== StudentTimetableStudyProgram::Normalstudium) {
            return;
        }

        if (StudentTimetableSubjectImport::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->forStudyProgram($studyProgram)
            ->exists()) {
            return;
        }

        $path = $this->latestJsonPath($authUser);

        if (! $path) {
            return;
        }

        $analysis = $this->analyzeJsonFile($path, $studyProgram);
        $filename = basename($path);

        $this->createSubjectImport(
            $authUser,
            $studyProgram,
            $filename,
            $filename,
            $this->relativeStoragePath($path),
            $path,
            $analysis,
            filemtime($path) ?: null,
        );
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function createSubjectImport(
        mixed $authUser,
        StudentTimetableStudyProgram $studyProgram,
        string $originalFilename,
        string $storedFilename,
        string $filePath,
        string $absolutePath,
        array $analysis,
        ?int $importedAtTimestamp = null,
    ): StudentTimetableSubjectImport {
        return StudentTimetableSubjectImport::create([
            'school_id' => $authUser->school_id,
            'schoolyear_id' => $authUser->schoolyear_id,
            'study_program' => $studyProgram->value,
            'user_id' => $authUser->id,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => $filePath,
            'file_size' => filesize($absolutePath) ?: 0,
            'analysis' => $analysis,
            'subjects_total' => (int) ($analysis['subjects_total'] ?? 0),
            'subject_rows_total' => count($analysis['subject_rows'] ?? []),
            'semesters_total' => count($analysis['semesters'] ?? []),
            'branches_total' => count($analysis['branches'] ?? []),
            'imported_at' => $importedAtTimestamp ? now()->setTimestamp($importedAtTimestamp) : now(),
        ]);
    }

    private function relativeStoragePath(string $path): string
    {
        $storageRoot = str_replace('\\', '/', storage_path());
        $normalizedPath = str_replace('\\', '/', $path);

        return Str::after($normalizedPath, "{$storageRoot}/");
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function replaceSubjectRowsFromAnalysis(
        mixed $authUser,
        StudentTimetableStudyProgram $studyProgram,
        array $analysis,
    ): void {
        $subjectRows = collect($analysis['subject_rows'] ?? [])
            ->values()
            ->map(fn (array $subjectRow, int $index): array => [
                'school_id' => $authUser->school_id,
                'schoolyear_id' => $authUser->schoolyear_id,
                'study_program' => $studyProgram->value,
                'semester' => $subjectRow['semester'] ?? null,
                'branch' => $this->normalizeSubjectBranch($subjectRow['branch'] ?? null),
                'json_code' => $subjectRow['json_code'] ?? null,
                'json_subject' => $subjectRow['json_subject'] ?? null,
                'name' => $this->canonicalSubjectName(
                    $subjectRow['name'] ?? null,
                    $subjectRow['json_code'] ?? null,
                    $subjectRow['json_subject'] ?? null,
                ),
                'hours_per_week' => $subjectRow['hours_per_week'] ?? null,
                'is_active' => true,
                'sort_order' => $index,
                'source' => 'json',
            ]);

        DB::transaction(function () use ($authUser, $studyProgram, $subjectRows): void {
            StudentTimetableSubjectRow::query()
                ->forStudyProgram($studyProgram)
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->delete();

            $subjectRows->each(fn (array $subjectRow): StudentTimetableSubjectRow => StudentTimetableSubjectRow::create($subjectRow));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectImportPayload(StudentTimetableSubjectImport $import): array
    {
        return [
            'id' => $import->id,
            'filename' => $import->stored_filename,
            'original_filename' => $import->original_filename,
            'stored_filename' => $import->stored_filename,
            'file_path' => $import->file_path,
            'size' => $import->file_size,
            'uploaded_at' => $import->imported_at?->toIso8601String(),
            'analysis' => $import->analysis ?? [],
            'subjects_total' => $import->subjects_total,
            'subject_rows_total' => $import->subject_rows_total,
            'semesters_total' => $import->semesters_total,
            'branches_total' => $import->branches_total,
            'study_program' => $import->study_program->value,
            'study_program_label' => $import->study_program->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectImportSummaryPayload(StudentTimetableSubjectImport $import): array
    {
        return [
            'id' => $import->id,
            'filename' => $import->stored_filename,
            'original_filename' => $import->original_filename,
            'stored_filename' => $import->stored_filename,
            'size' => $import->file_size,
            'uploaded_at' => $import->imported_at?->toIso8601String(),
            'subjects_total' => $import->subjects_total,
            'subject_rows_total' => $import->subject_rows_total,
            'semesters_total' => $import->semesters_total,
            'branches_total' => $import->branches_total,
            'study_program' => $import->study_program->value,
            'study_program_label' => $import->study_program->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeSubjectDatasetMetadata(
        mixed $authUser,
        StudentTimetableStudyProgram $studyProgram,
    ): array {
        $baseQuery = StudentTimetableSubjectRow::query()
            ->forStudyProgram($studyProgram)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->where('is_active', true);

        return [
            'name' => 'Aktive Fächer',
            'table' => 'student_timetable_subject_rows',
            'study_program' => $studyProgram->value,
            'study_program_label' => $studyProgram->label(),
            'subjects_count' => (clone $baseQuery)
                ->whereNotNull('name')
                ->distinct('name')
                ->count('name'),
            'subject_rows_count' => (clone $baseQuery)->count(),
            'semesters_count' => (clone $baseQuery)
                ->whereNotNull('semester')
                ->distinct('semester')
                ->count('semester'),
            'branches_count' => (clone $baseQuery)
                ->whereNotNull('branch')
                ->distinct('branch')
                ->count('branch'),
            'updated_at' => (clone $baseQuery)->max('updated_at'),
        ];
    }

    private function seedEditableDataFromLatestImportIfMissing(
        mixed $authUser,
        StudentTimetableStudyProgram $studyProgram,
    ): void {
        $this->importLegacyJsonIfMissing($authUser, $studyProgram);

        $hasSubjectRows = StudentTimetableSubjectRow::query()
            ->forStudyProgram($studyProgram)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->exists();

        $import = StudentTimetableSubjectImport::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->forStudyProgram($studyProgram)
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->first();

        if (! $import) {
            return;
        }

        $subjectRows = $import->analysis['subject_rows'] ?? [];

        if ($hasSubjectRows) {
            $this->refreshJsonSubjectRowNames($authUser, $studyProgram, $subjectRows);
        } else {
            $this->replaceSubjectRowsFromAnalysis($authUser, $studyProgram, $import->analysis ?? []);
        }

        $this->seedDefaultSubjectMappings($authUser, collect($subjectRows)->pluck('json_subject')->filter()->unique()->values()->all());
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     */
    private function refreshJsonSubjectRowNames(
        mixed $authUser,
        StudentTimetableStudyProgram $studyProgram,
        array $subjectRows,
    ): void {
        $subjectRowsByIdentity = collect($subjectRows)
            ->keyBy(fn (array $subjectRow): string => $this->subjectRowStableIdentitySignature($subjectRow));

        StudentTimetableSubjectRow::query()
            ->forStudyProgram($studyProgram)
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->get()
            ->each(function (StudentTimetableSubjectRow $row) use ($subjectRowsByIdentity): void {
                $subjectRow = $subjectRowsByIdentity->get($this->subjectRowStableIdentitySignature([
                    'semester' => $row->semester,
                    'branch' => $this->normalizeSubjectBranch($row->branch),
                    'json_code' => $row->json_code,
                    'json_subject' => $row->json_subject,
                ]));

                if (! $subjectRow) {
                    return;
                }

                $updates = [];

                if ($this->isStaleJsonSubjectName($row->name, $subjectRow)) {
                    $updates['name'] = $subjectRow['name'] ?? null;
                }

                $expectedHours = $subjectRow['hours_per_week'] ?? null;
                if (
                    $row->source === 'json'
                    && $expectedHours !== null
                    && $this->normalizedSubjectHours($row->hours_per_week) !== $this->normalizedSubjectHours($expectedHours)
                ) {
                    $updates['hours_per_week'] = $expectedHours;
                }

                if ($updates === []) {
                    return;
                }

                $row->update($updates);
            });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function subjectRowStableIdentitySignature(array $row): string
    {
        return Str::lower(implode('|', [
            $row['semester'] ?? '',
            $row['branch'] ?? '',
            $row['json_code'] ?? '',
            $row['json_subject'] ?? '',
        ]));
    }

    private function normalizedSubjectHours(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $subjectRow
     */
    private function isStaleJsonSubjectName(mixed $currentName, array $subjectRow): bool
    {
        $currentName = $this->emptyToNull($currentName);
        $expectedName = $this->emptyToNull($subjectRow['name'] ?? null);

        if (! $expectedName || $currentName === $expectedName) {
            return false;
        }

        $staleNames = collect([
            $subjectRow['json_subject'] ?? null,
            $subjectRow['json_code'] ?? null,
            $this->subjectCodeWithoutModule($subjectRow['json_code'] ?? null),
        ])
            ->map(fn (mixed $value): ?string => $this->emptyToNull($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ! $currentName || in_array($currentName, $staleNames, true);
    }

    private function seedDefaultSubjectMappings(mixed $authUser, array $jsonSubjects): void
    {
        $defaults = [
            'GS' => 'GPB',
            'GW' => 'GWB',
            'ME' => 'MU',
            'ÖKO' => 'OKON',
            'LPT' => 'LET',
        ];

        collect($defaults)
            ->filter(fn (string $ttSubject, string $jsonSubject): bool => in_array($jsonSubject, $jsonSubjects, true))
            ->each(function (string $ttSubject, string $jsonSubject) use ($authUser): void {
                $mapping = StudentTimetableSubjectMapping::firstOrCreate([
                    'school_id' => $authUser->school_id,
                    'schoolyear_id' => $authUser->schoolyear_id,
                    'json_subject' => $jsonSubject,
                    'tt_subject' => $ttSubject,
                ], [
                    'note' => null,
                    'is_active' => true,
                    'source' => 'suggestion',
                ]);

                if ($mapping->source === 'suggestion' && $mapping->note === 'Vorschlag') {
                    $mapping->update(['note' => null]);
                }
            });
    }

    private function latestJsonPath(mixed $authUser): ?string
    {
        return $this->jsonFiles($authUser)->first();
    }

    /**
     * @return array{subjects: list<array<string, mixed>>, mappings?: list<array<string, mixed>>}
     */
    private function editableSettingsData(
        mixed $authUser,
        StudentTimetableStudyProgram $studyProgram,
        bool $subjectsOnly = false,
    ): array {
        $settingsData = [
            'study_program' => $studyProgram->value,
            'study_program_label' => $studyProgram->label(),
            'subjects' => StudentTimetableSubjectRow::query()
                ->forStudyProgram($studyProgram)
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->orderBy('sort_order')
                ->orderBy('semester')
                ->orderBy('branch')
                ->get()
                ->map(fn (StudentTimetableSubjectRow $row): array => [
                    'id' => $row->id,
                    'semester' => $row->semester,
                    'branch' => $this->normalizeSubjectBranch($row->branch),
                    'json_code' => $row->json_code,
                    'json_subject' => $row->json_subject,
                    'name' => $this->canonicalSubjectName($row->name, $row->json_code, $row->json_subject),
                    'hours_per_week' => $row->hours_per_week,
                    'is_active' => $row->is_active,
                    'source' => $row->source,
                ])
                ->values()
                ->all(),
        ];

        if ($subjectsOnly) {
            return $settingsData;
        }

        $settingsData['mappings'] = StudentTimetableSubjectMapping::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->orderBy('json_subject')
            ->orderBy('tt_subject')
            ->get()
            ->map(fn (StudentTimetableSubjectMapping $mapping): array => [
                'id' => $mapping->id,
                'json_subject' => $mapping->json_subject,
                'tt_subject' => $mapping->tt_subject,
                'note' => $mapping->note,
                'is_active' => $mapping->is_active,
                'source' => $mapping->source,
            ])
            ->values()
            ->all();

        return $settingsData;
    }

    private function emptyToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function canonicalSubjectName(mixed $name, mixed $jsonCode = null, mixed $jsonSubject = null): ?string
    {
        $subjectKey = $this->subjectCodeWithoutModule($this->emptyToNull($jsonSubject))
            ?: $this->subjectCodeWithoutModule($this->emptyToNull($jsonCode));
        $subjectKey = $subjectKey ? Str::upper($subjectKey) : null;

        if ($subjectKey && array_key_exists($subjectKey, self::CANONICAL_SUBJECT_NAMES)) {
            return self::CANONICAL_SUBJECT_NAMES[$subjectKey];
        }

        return $this->emptyToNull($name);
    }

    private function normalizeSubjectBranch(mixed $value): ?string
    {
        $value = $this->emptyToNull($value);

        return $value === 'common' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function subjectRowHasContent(array $subject): bool
    {
        return (bool) ($subject['semester']
            || $subject['branch']
            || $subject['json_code']
            || $subject['json_subject']
            || $subject['name']
            || $subject['hours_per_week']);
    }
}
