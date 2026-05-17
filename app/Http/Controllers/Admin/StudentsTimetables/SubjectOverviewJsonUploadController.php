<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SubjectOverviewJsonUploadController extends Controller
{
    public function index(): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $latestPath = $this->jsonFiles($authUser)->first();

        if ($latestPath) {
            $this->deleteOtherJsonFiles($authUser, $latestPath);
        }

        $files = collect($latestPath ? [$latestPath] : [])
            ->map(fn (string $path): array => [
                'filename' => basename($path),
                'size' => filesize($path) ?: 0,
                'uploaded_at' => date('c', filemtime($path) ?: time()),
                'analysis' => $this->analyzeJsonFile($path),
            ])
            ->values();

        return response()->json([
            'data' => $files,
            'total' => $files->count(),
        ]);
    }

    public function settings(): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->seedEditableDataFromLatestJsonIfMissing($authUser);

        return response()->json([
            'data' => $this->editableSettingsData($authUser),
        ]);
    }

    public function updateSubjects(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

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
                'semester' => $subject['semester'] ?? null,
                'branch' => $this->normalizeSubjectBranch($subject['branch'] ?? null),
                'json_code' => $this->emptyToNull($subject['json_code'] ?? null),
                'json_subject' => $this->emptyToNull($subject['json_subject'] ?? null),
                'name' => $this->emptyToNull($subject['name'] ?? null),
                'hours_per_week' => $subject['hours_per_week'] ?? null,
                'is_active' => (bool) ($subject['is_active'] ?? true),
                'sort_order' => $index,
                'source' => 'manual',
            ])
            ->filter(fn (array $subject): bool => $this->subjectRowHasContent($subject))
            ->values();

        DB::transaction(function () use ($authUser, $subjects): void {
            StudentTimetableSubjectRow::query()
                ->where('school_id', $authUser->school_id)
                ->where('schoolyear_id', $authUser->schoolyear_id)
                ->delete();

            $subjects->each(fn (array $subject): StudentTimetableSubjectRow => StudentTimetableSubjectRow::create($subject));
        });

        return response()->json([
            'data' => $this->editableSettingsData($authUser),
        ]);
    }

    public function updateMappings(Request $request): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

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
            'data' => $this->editableSettingsData($authUser),
        ]);
    }

    public function upload(FileUploadService $fileUploadService): Response
    {
        if (! $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensureJson();

        $id = $fileUploadService->upload();

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadNext(Request $request, FileUploadService $fileUploadService): Response
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        $this->ensureJson();

        $uploadPath = $this->storageDirectory($authUser->school_id, $authUser->schoolyear_id);
        $storedName = $this->storedFilename($request->header('Upload-Name'));

        $result = $fileUploadService->uploadNext($request, $uploadPath, $storedName);

        if ($result instanceof Response) {
            return $result;
        }

        $storedPath = storage_path("{$uploadPath}/{$result}");

        $this->ensureStoredFileContainsJson("{$uploadPath}/{$result}");
        $this->deleteOtherJsonFiles($authUser, $storedPath);
        $this->seedEditableDataFromJsonPath($authUser, $storedPath);

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
     * @return array{subjects_total: int, semesters: list<array<string, mixed>>, branches: list<array<string, mixed>>, subject_rows: list<array<string, mixed>>}
     */
    private function analyzeJsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        $data = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($data)) {
            return [
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
     * @param  array{name: string, short_name: ?string, json_subject: ?string}  $subject
     * @return array<string, mixed>
     */
    private function subjectRowFromNode(array $node, array $subject, string $semesterKey, ?string $branch): array
    {
        return [
            'semester' => $this->semesterNumber($semesterKey),
            'branch' => $this->normalizeSubjectBranch($branch),
            'json_code' => $subject['short_name'] ?: $subject['name'],
            'json_subject' => $subject['json_subject'] ?: $subject['name'],
            'name' => $subject['name'],
            'hours_per_week' => $this->firstNumericValue($node, ['hours_per_week', 'hours', 'stunden', 'wochenstunden']),
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
     * @return list<array{name: string, short_name: ?string, json_subject: ?string}>
     */
    private function subjectsFromNode(array $node, ?string $contextKey, ?string $semester, array $courseAbbreviations): array
    {
        $rawSubject = $this->firstStringValue($node, ['fach', 'subject']);
        $explicitName = $this->firstStringValue($node, ['subject_name', 'name', 'long_name', 'title', 'bezeichnung']);
        $shortName = $this->firstStringValue($node, ['short_name', 'shortName', 'kurzname', 'kuerzel', 'code']);
        $jsonSubject = $rawSubject ?: $this->subjectCodeWithoutModule($shortName);
        $abbreviationKey = $rawSubject ?: $this->subjectCodeWithoutModule($shortName);
        $subjectCodes = $this->subjectCodes($shortName, $abbreviationKey, $courseAbbreviations);

        if (! $semester && ! $this->isSubjectContext($contextKey) && ! $shortName) {
            return [];
        }

        return collect($subjectCodes)
            ->map(function (?string $subjectCode) use ($rawSubject, $explicitName, $jsonSubject, $abbreviationKey, $courseAbbreviations): ?array {
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

    private function storageDirectory(int|string|null $schoolId, int|string|null $schoolyearId): string
    {
        return "app/private/{$schoolId}/student-timetable-subjects/{$schoolyearId}";
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

    private function deleteOtherJsonFiles(mixed $authUser, string $keptPath): void
    {
        $keptPath = realpath($keptPath) ?: $keptPath;

        $this->jsonFiles($authUser)
            ->reject(fn (string $path): bool => (realpath($path) ?: $path) === $keptPath)
            ->each(fn (string $path): bool => @unlink($path));
    }

    private function seedEditableDataFromLatestJsonIfMissing(mixed $authUser): void
    {
        $path = $this->latestJsonPath($authUser);

        if (! $path) {
            return;
        }

        $this->seedEditableDataFromJsonPath($authUser, $path);
    }

    private function seedEditableDataFromJsonPath(mixed $authUser, string $path): void
    {
        $analysis = $this->analyzeJsonFile($path);
        $subjectRows = $analysis['subject_rows'] ?? [];

        if (! StudentTimetableSubjectRow::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->exists()) {
            collect($subjectRows)
                ->values()
                ->each(function (array $subjectRow, int $index) use ($authUser): void {
                    StudentTimetableSubjectRow::create([
                        'school_id' => $authUser->school_id,
                        'schoolyear_id' => $authUser->schoolyear_id,
                        'semester' => $subjectRow['semester'] ?? null,
                        'branch' => $this->normalizeSubjectBranch($subjectRow['branch'] ?? null),
                        'json_code' => $subjectRow['json_code'] ?? null,
                        'json_subject' => $subjectRow['json_subject'] ?? null,
                        'name' => $subjectRow['name'] ?? null,
                        'hours_per_week' => $subjectRow['hours_per_week'] ?? null,
                        'is_active' => true,
                        'sort_order' => $index,
                        'source' => 'json',
                    ]);
                });
        } else {
            $this->refreshJsonSubjectRowNames($authUser, $subjectRows);
        }

        $this->seedDefaultSubjectMappings($authUser, collect($subjectRows)->pluck('json_subject')->filter()->unique()->values()->all());
    }

    /**
     * @param  list<array<string, mixed>>  $subjectRows
     */
    private function refreshJsonSubjectRowNames(mixed $authUser, array $subjectRows): void
    {
        $subjectRowsByIdentity = collect($subjectRows)
            ->keyBy(fn (array $subjectRow): string => $this->subjectRowIdentitySignature($subjectRow));

        StudentTimetableSubjectRow::query()
            ->where('school_id', $authUser->school_id)
            ->where('schoolyear_id', $authUser->schoolyear_id)
            ->get()
            ->each(function (StudentTimetableSubjectRow $row) use ($subjectRowsByIdentity): void {
                $subjectRow = $subjectRowsByIdentity->get($this->subjectRowIdentitySignature([
                    'semester' => $row->semester,
                    'branch' => $this->normalizeSubjectBranch($row->branch),
                    'json_code' => $row->json_code,
                    'json_subject' => $row->json_subject,
                    'hours_per_week' => $row->hours_per_week,
                ]));

                if (! $subjectRow || ! $this->isStaleJsonSubjectName($row->name, $subjectRow)) {
                    return;
                }

                $row->update(['name' => $subjectRow['name'] ?? null]);
            });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function subjectRowIdentitySignature(array $row): string
    {
        return Str::lower(implode('|', [
            $row['semester'] ?? '',
            $row['branch'] ?? '',
            $row['json_code'] ?? '',
            $row['json_subject'] ?? '',
            $this->normalizedSubjectHours($row['hours_per_week'] ?? null),
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
     * @return array{subjects: list<array<string, mixed>>, mappings: list<array<string, mixed>>}
     */
    private function editableSettingsData(mixed $authUser): array
    {
        return [
            'subjects' => StudentTimetableSubjectRow::query()
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
                    'name' => $row->name,
                    'hours_per_week' => $row->hours_per_week,
                    'is_active' => $row->is_active,
                    'source' => $row->source,
                ])
                ->values()
                ->all(),
            'mappings' => StudentTimetableSubjectMapping::query()
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
                ->all(),
        ];
    }

    private function emptyToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
