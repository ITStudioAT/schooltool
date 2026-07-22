<?php

namespace App\Services;

use App\Models\Import116Run;
use App\Models\Schoolyear;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TeachingTestEnvironmentService
{
    public const SOURCE_SCHOOLYEAR = '2025/26';

    public const TARGET_SCHOOLYEAR = '2026/27';

    private const IMPORT_RUN_SOURCE = 'temporary-teaching-test-environment-2026-27';

    /** @var list<string> */
    private const ADDITIONAL_TARGET_SCHOOLYEAR_TABLES = [
        'register_date_bookings',
        'register_dates',
        'registers',
        'student_timetable_recognition_rows',
        'student_timetable_entries',
        'student_timetable_evaluation_settings',
        'student_timetable_overview_selections',
        'student_timetable_personal_timetables',
        'student_timetable_profile_selections',
        'student_timetable_published_timetables',
        'student_timetable_remembered_tt_entries',
        'student_timetable_subject_mappings',
        'student_timetable_subject_rows',
        'student_timetable_v2_states',
        'student_timetable_recognition_imports',
        'student_timetable_subject_imports',
        'timetable_imports',
        'abas',
    ];

    /** @var list<string> */
    private const SCHOOLYEAR_SETTING_COLUMNS = [
        'teaching_behaviour_by_schoolyear',
        'teaching_notifications_by_schoolyear',
        'teaching_grade_columns_by_schoolyear',
        'teaching_student_grade_columns_by_schoolyear',
    ];

    /**
     * @return array<string, mixed>
     */
    public function status(User $user): array
    {
        [$sourceSchoolyear, $targetSchoolyear] = $this->schoolyearsFor($user);

        return [
            'source_schoolyear' => $this->schoolyearPayload($sourceSchoolyear),
            'target_schoolyear' => $this->schoolyearPayload($targetSchoolyear),
            'is_configured' => $this->hasProvisioningMarker((int) $user->school_id, (int) $targetSchoolyear->id),
            'source_import116_count' => $this->importCount((int) $user->school_id, (int) $sourceSchoolyear->id),
            'target_import116_count' => $this->importCount((int) $user->school_id, (int) $targetSchoolyear->id),
            'target_teaching_record_count' => $this->targetResettableTeachingRecordCount((int) $user->school_id, (int) $targetSchoolyear->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setup(User $user): array
    {
        [$sourceSchoolyear, $targetSchoolyear] = $this->schoolyearsFor($user);
        $schoolId = (int) $user->school_id;
        $sourceSchoolyearId = (int) $sourceSchoolyear->id;
        $targetSchoolyearId = (int) $targetSchoolyear->id;

        $sourceRows = DB::table('import116')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $sourceSchoolyearId)
            ->orderBy('id')
            ->get();

        if ($sourceRows->isEmpty()) {
            throw new DomainException('Für 2025/26 sind keine Import-116-Daten vorhanden.');
        }

        $duplicateStudentCode = $sourceRows
            ->groupBy(fn (object $row): string => (string) $row->student_code)
            ->first(fn (Collection $rows): bool => $rows->count() > 1)?->first()?->student_code;

        if ($duplicateStudentCode) {
            throw new DomainException("Die Schülerkennzahl {$duplicateStudentCode} ist in 2025/26 mehrfach vorhanden.");
        }

        $filesToDelete = [];

        $result = DB::transaction(function () use (
            $user,
            $schoolId,
            $sourceSchoolyearId,
            $targetSchoolyearId,
            $sourceRows,
            &$filesToDelete,
        ): array {
            Schoolyear::query()->whereKey($targetSchoolyearId)->lockForUpdate()->firstOrFail();

            $purgeResult = $this->purgeTargetSchoolyear($schoolId, $sourceSchoolyearId, $targetSchoolyearId);
            $filesToDelete = $purgeResult['files'];

            $now = now();
            $password = Hash::make(Str::random(64));
            $reusableUserIds = $this->reusableUserIdsBySourceImport($sourceRows, $schoolId);
            $testUserEmails = [];

            $sourceRows
                ->reject(fn (object $sourceRow): bool => $reusableUserIds->has((int) $sourceRow->id))
                ->chunk(250)
                ->each(function (Collection $chunk) use (
                    $schoolId,
                    $targetSchoolyearId,
                    $now,
                    $password,
                    &$testUserEmails,
                ): void {
                    $rows = $chunk->map(function (object $sourceRow) use ($schoolId, $targetSchoolyearId, $now, $password, &$testUserEmails): array {
                        $email = $this->testUserEmail($schoolId, (int) $sourceRow->id);
                        $testUserEmails[(int) $sourceRow->id] = $email;

                        return [
                            'school_id' => $schoolId,
                            'schoolyear_id' => $targetSchoolyearId,
                            'email' => $email,
                            'password' => $password,
                            'first_name' => $sourceRow->first_name,
                            'last_name' => $sourceRow->last_name,
                            'phone' => $sourceRow->phone_1,
                            'sex' => $sourceRow->sex,
                            'schoolclass' => $sourceRow->class,
                            'uuid' => (string) Str::uuid(),
                            'is_active' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    DB::table('users')->insert($rows);
                });

            $testUserIds = DB::table('users')
                ->where('school_id', $schoolId)
                ->whereIn('email', array_values($testUserEmails))
                ->pluck('id', 'email');

            $sourceRows->chunk(250)->each(function (Collection $chunk) use (
                $user,
                $schoolId,
                $targetSchoolyearId,
                $now,
                $testUserEmails,
                $testUserIds,
                $reusableUserIds,
            ): void {
                DB::table('import116')->insert($chunk->map(function (object $sourceRow) use (
                    $user,
                    $schoolId,
                    $targetSchoolyearId,
                    $now,
                    $testUserEmails,
                    $testUserIds,
                    $reusableUserIds,
                ): array {
                    $reusableUserId = $reusableUserIds->get((int) $sourceRow->id);

                    return [
                        'school_id' => $schoolId,
                        'schoolyear_id' => $targetSchoolyearId,
                        'class' => $sourceRow->class,
                        'school_level' => $sourceRow->school_level,
                        'attendance_year' => $sourceRow->attendance_year,
                        'religion' => $sourceRow->religion,
                        'student_code' => $sourceRow->student_code,
                        'last_name' => $sourceRow->last_name,
                        'first_name' => $sourceRow->first_name,
                        'email' => $sourceRow->email,
                        'phone_1' => $sourceRow->phone_1,
                        'phone_2' => $sourceRow->phone_2,
                        'sex' => $sourceRow->sex,
                        'birth_date' => $sourceRow->birth_date,
                        'mother_name' => $sourceRow->mother_name,
                        'mother_email' => $sourceRow->mother_email,
                        'mother_phone_1' => $sourceRow->mother_phone_1,
                        'mother_phone_2' => $sourceRow->mother_phone_2,
                        'father_name' => $sourceRow->father_name,
                        'father_email' => $sourceRow->father_email,
                        'father_phone_1' => $sourceRow->father_phone_1,
                        'father_phone_2' => $sourceRow->father_phone_2,
                        'import_date' => $now,
                        'exists_date' => $now,
                        'import_user_id' => (int) $user->id,
                        'user_id' => $reusableUserId
                            ? (int) $reusableUserId
                            : (int) $testUserIds->get($testUserEmails[(int) $sourceRow->id]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all());
            });

            DB::update(
                'UPDATE users INNER JOIN import116 ON import116.user_id = users.id SET users.import116_id = import116.id, users.schoolyear_id = ?, users.updated_at = ? WHERE users.school_id = ? AND import116.school_id = ? AND import116.schoolyear_id = ?',
                [$targetSchoolyearId, $now, $schoolId, $schoolId, $targetSchoolyearId]
            );

            Import116Run::query()->create([
                'school_id' => $schoolId,
                'schoolyear_id' => $targetSchoolyearId,
                'user_id' => $user->id,
                'source_path' => self::IMPORT_RUN_SOURCE,
                'status' => 'completed',
                'started_at' => $now,
                'finished_at' => $now,
                'counts' => [
                    'inserted' => $sourceRows->count(),
                    'updated' => 0,
                    'deleted' => (int) $purgeResult['deleted_records'],
                ],
                'report_summary' => [
                    'temporary_test_environment' => true,
                    'source_schoolyear_id' => $sourceSchoolyearId,
                    'target_schoolyear_id' => $targetSchoolyearId,
                ],
            ]);

            DB::table('users')->where('id', $user->id)->update([
                'schoolyear_id' => $targetSchoolyearId,
                'updated_at' => $now,
            ]);

            return [
                'copied_import116_records' => $sourceRows->count(),
                'created_test_users' => count($testUserEmails),
                'reused_user_accounts' => $reusableUserIds->count(),
                'removed_target_records' => (int) $purgeResult['deleted_records'],
                'selected_schoolyear_id' => $targetSchoolyearId,
            ];
        }, attempts: 3);

        $this->deleteFiles($filesToDelete);

        return $result + $this->status($user->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(User $user): array
    {
        [$sourceSchoolyear, $targetSchoolyear] = $this->schoolyearsFor($user);
        $schoolId = (int) $user->school_id;
        $sourceSchoolyearId = (int) $sourceSchoolyear->id;
        $targetSchoolyearId = (int) $targetSchoolyear->id;
        $filesToDelete = [];

        $result = DB::transaction(function () use ($schoolId, $sourceSchoolyearId, $targetSchoolyearId, &$filesToDelete): array {
            Schoolyear::query()->whereKey($targetSchoolyearId)->lockForUpdate()->firstOrFail();

            $purgeResult = $this->purgeTargetSchoolyear($schoolId, $sourceSchoolyearId, $targetSchoolyearId);
            $filesToDelete = $purgeResult['files'];

            return [
                'deleted_records' => (int) $purgeResult['deleted_records'],
                'deleted_test_users' => (int) $purgeResult['deleted_test_users'],
                'selected_schoolyear_id' => $sourceSchoolyearId,
            ];
        }, attempts: 3);

        $this->deleteFiles($filesToDelete);

        return $result + $this->status($user->fresh());
    }

    /**
     * @return array{0:Schoolyear,1:Schoolyear}
     */
    private function schoolyearsFor(User $user): array
    {
        $schoolyears = Schoolyear::query()
            ->where('school_id', $user->school_id)
            ->whereIn('concerns', [self::SOURCE_SCHOOLYEAR, self::TARGET_SCHOOLYEAR])
            ->get()
            ->keyBy('concerns');

        $sourceSchoolyear = $schoolyears->get(self::SOURCE_SCHOOLYEAR);
        $targetSchoolyear = $schoolyears->get(self::TARGET_SCHOOLYEAR);

        if (! $sourceSchoolyear || ! $targetSchoolyear) {
            throw new DomainException('Die Schuljahre 2025/26 und 2026/27 müssen angelegt sein.');
        }

        return [$sourceSchoolyear, $targetSchoolyear];
    }

    /**
     * @return array{id:int,label:string}
     */
    private function schoolyearPayload(Schoolyear $schoolyear): array
    {
        return [
            'id' => (int) $schoolyear->id,
            'label' => (string) ($schoolyear->concerns ?: $schoolyear->name),
        ];
    }

    private function hasProvisioningMarker(int $schoolId, int $targetSchoolyearId): bool
    {
        return Import116Run::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $targetSchoolyearId)
            ->where('source_path', self::IMPORT_RUN_SOURCE)
            ->where('status', 'completed')
            ->exists();
    }

    private function importCount(int $schoolId, int $schoolyearId): int
    {
        return DB::table('import116')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->count();
    }

    /**
     * @return Collection<int, int>
     */
    private function reusableUserIdsBySourceImport(Collection $sourceRows, int $schoolId): Collection
    {
        $sourceUserIds = $sourceRows
            ->pluck('user_id')
            ->filter(fn (mixed $userId): bool => (int) $userId > 0)
            ->map(fn (mixed $userId): int => (int) $userId)
            ->values();
        $sourceEmails = $sourceRows
            ->pluck('email')
            ->map(fn (mixed $email): string => mb_strtolower(trim((string) $email)))
            ->filter()
            ->values();

        $candidates = User::query()
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($sourceUserIds, $sourceEmails): void {
                if ($sourceUserIds->isNotEmpty()) {
                    $query->whereIn('id', $sourceUserIds->all());
                }

                if ($sourceEmails->isNotEmpty()) {
                    $method = $sourceUserIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}(DB::raw('LOWER(email)'), $sourceEmails->all());
                }
            })
            ->get();

        $candidatesById = $candidates->keyBy(fn (User $candidate): int => (int) $candidate->id);
        $candidatesByEmail = $candidates
            ->reject(fn (User $candidate): bool => str_starts_with((string) $candidate->email, 'teaching-test-2627-'))
            ->keyBy(fn (User $candidate): string => mb_strtolower(trim((string) $candidate->email)));

        return $sourceRows->mapWithKeys(function (object $sourceRow) use ($candidatesById, $candidatesByEmail): array {
            $candidate = $candidatesById->get((int) ($sourceRow->user_id ?? 0))
                ?? $candidatesByEmail->get(mb_strtolower(trim((string) ($sourceRow->email ?? ''))));

            if (! $candidate || str_starts_with((string) $candidate->email, 'teaching-test-2627-')) {
                return [];
            }

            return [(int) $sourceRow->id => (int) $candidate->id];
        });
    }

    private function targetResettableTeachingRecordCount(int $schoolId, int $schoolyearId): int
    {
        return collect([
            'teaching_courses',
            'teaching_curricula',
            'teaching_schemas',
            'teaching_school_hours',
        ])->sum(fn (string $table): int => DB::table($table)
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->count());
    }

    /**
     * @return array{deleted_records:int,deleted_test_users:int,files:list<array{disk:string,path:string}>}
     */
    private function purgeTargetSchoolyear(int $schoolId, int $sourceSchoolyearId, int $targetSchoolyearId): array
    {
        $courseIds = $this->idsForScope('teaching_courses', $schoolId, $targetSchoolyearId);
        $courseDateIds = $this->idsFromTable('teaching_course_dates', 'teaching_course_id', $courseIds);
        $courseDateMaterialIds = $this->idsFromTable('teaching_course_date_materials', 'teaching_course_date_id', $courseDateIds);
        $curriculumIds = $this->idsForScope('teaching_curricula', $schoolId, $targetSchoolyearId);
        $importRunIds = $this->idsForScope('import116_runs', $schoolId, $targetSchoolyearId);
        $importIds = $this->idsForScope('import116', $schoolId, $targetSchoolyearId);
        $userGroupIds = DB::table('user_groups')
            ->where('school_id', $schoolId)
            ->whereIn('teaching_course_id', $courseIds ?: [-1])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $testUserIds = DB::table('users')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $targetSchoolyearId)
            ->where('email', 'like', $this->testUserEmailLikePattern($schoolId))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $files = $this->filesForTarget($schoolId, $targetSchoolyearId, $courseDateMaterialIds, $curriculumIds);
        $deletedRecords = 0;

        $deletedRecords += $this->deleteWhereIn('user_group_members', 'user_group_id', $userGroupIds);
        $deletedRecords += $this->deleteWhereIn('user_groups', 'id', $userGroupIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_date_material_attachments', 'teaching_course_date_material_id', $courseDateMaterialIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_date_materials', 'id', $courseDateMaterialIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_work_group_students', 'teaching_course_id', $courseIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_student_category_evaluations', 'teaching_course_id', $courseIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_behaviour_entries', 'teaching_course_id', $courseIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_student_entries', 'teaching_course_id', $courseIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_works', 'teaching_course_id', $courseIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_students', 'teaching_course_id', $courseIds);
        $deletedRecords += $this->deleteWhereIn('teaching_course_dates', 'id', $courseDateIds);
        $deletedRecords += $this->deleteWhereIn('teaching_courses', 'id', $courseIds);

        $deletedRecords += DB::table('teaching_imported_curricula')
            ->where('school_id', $schoolId)
            ->whereIn('adopted_curriculum_id', $curriculumIds ?: [-1])
            ->delete();
        $deletedRecords += $this->deleteWhereIn('teaching_curriculum_documents', 'teaching_curriculum_id', $curriculumIds);
        $deletedRecords += $this->deleteWhereIn('teaching_curricula', 'id', $curriculumIds);

        foreach (['teaching_schemas', 'teaching_school_hours'] as $table) {
            $deletedRecords += DB::table($table)
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $targetSchoolyearId)
                ->delete();
        }

        DB::table('restaurant_menu_plan_bookings')
            ->whereIn('import116_id', $importIds ?: [-1])
            ->update(['import116_id' => null, 'updated_at' => now()]);
        DB::table('users')
            ->where('school_id', $schoolId)
            ->whereIn('import116_id', $importIds ?: [-1])
            ->update(['import116_id' => null, 'updated_at' => now()]);

        $deletedRecords += $this->deleteWhereIn('import116_run_changes', 'import116_run_id', $importRunIds);
        $deletedRecords += $this->deleteWhereIn('import116_runs', 'id', $importRunIds);
        $deletedRecords += $this->deleteWhereIn('import116', 'id', $importIds);

        $deletedRecords += DB::table('teaching_backup_restore_runs')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $targetSchoolyearId)
            ->delete();
        $deletedRecords += DB::table('teaching_backups')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $targetSchoolyearId)
            ->delete();

        foreach (self::ADDITIONAL_TARGET_SCHOOLYEAR_TABLES as $table) {
            $deletedRecords += DB::table($table)
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $targetSchoolyearId)
                ->delete();
        }

        $deletedRecords += DB::table('user_group_members')
            ->where('school_id', $schoolId)
            ->where('source_schoolyear_id', $targetSchoolyearId)
            ->delete();

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('model_id', $testUserIds ?: [-1])
            ->delete();
        DB::table('model_has_permissions')
            ->where('model_type', User::class)
            ->whereIn('model_id', $testUserIds ?: [-1])
            ->delete();
        $deletedTestUsers = $this->deleteWhereIn('users', 'id', $testUserIds);

        $this->removeTargetSchoolyearSettings($schoolId, $targetSchoolyearId);

        DB::table('users')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $targetSchoolyearId)
            ->update([
                'schoolyear_id' => $sourceSchoolyearId,
                'updated_at' => now(),
            ]);

        DB::table('school_tools')
            ->where('school_id', $schoolId)
            ->where('active_schoolyear_id', $targetSchoolyearId)
            ->update([
                'active_schoolyear_id' => $sourceSchoolyearId,
                'updated_at' => now(),
            ]);

        DB::update(
            'UPDATE users INNER JOIN import116 ON import116.user_id = users.id SET users.import116_id = import116.id, users.updated_at = ? WHERE users.school_id = ? AND import116.school_id = ? AND import116.schoolyear_id = ?',
            [now(), $schoolId, $schoolId, $sourceSchoolyearId]
        );

        return [
            'deleted_records' => $deletedRecords,
            'deleted_test_users' => $deletedTestUsers,
            'files' => $files,
        ];
    }

    private function removeTargetSchoolyearSettings(int $schoolId, int $targetSchoolyearId): void
    {
        User::query()
            ->where('school_id', $schoolId)
            ->lazyById()
            ->each(function (User $user) use ($targetSchoolyearId): void {
                $changed = false;

                foreach (self::SCHOOLYEAR_SETTING_COLUMNS as $column) {
                    $settings = $user->{$column};

                    if (! is_array($settings) || ! array_key_exists($targetSchoolyearId, $settings)) {
                        continue;
                    }

                    unset($settings[$targetSchoolyearId]);
                    $user->{$column} = $settings;
                    $changed = true;
                }

                if ($changed) {
                    $user->save();
                }
            });
    }

    /**
     * @param  list<int>  $courseDateMaterialIds
     * @param  list<int>  $curriculumIds
     * @return list<array{disk:string,path:string}>
     */
    private function filesForTarget(int $schoolId, int $schoolyearId, array $courseDateMaterialIds, array $curriculumIds): array
    {
        $files = DB::table('teaching_course_date_material_attachments')
            ->whereIn('teaching_course_date_material_id', $courseDateMaterialIds ?: [-1])
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->map(fn (string $path): array => ['disk' => 'local', 'path' => $path]);

        $files = $files->merge(DB::table('teaching_curriculum_documents')
            ->whereIn('teaching_curriculum_id', $curriculumIds ?: [-1])
            ->whereNotNull('file_path')
            ->get(['storage_disk', 'file_path'])
            ->map(fn (object $document): array => [
                'disk' => (string) ($document->storage_disk ?: 'local'),
                'path' => (string) $document->file_path,
            ]));

        $files = $files->merge(DB::table('aba_attachments')
            ->join('abas', 'abas.id', '=', 'aba_attachments.aba_id')
            ->where('abas.school_id', $schoolId)
            ->where('abas.schoolyear_id', $schoolyearId)
            ->get(['aba_attachments.disk', 'aba_attachments.path'])
            ->map(fn (object $attachment): array => [
                'disk' => (string) ($attachment->disk ?: 'local'),
                'path' => (string) $attachment->path,
            ]));

        $storageFileSources = [
            'student_timetable_recognition_imports' => "app/private/{$schoolId}/recognition-imports/{$schoolyearId}/",
            'student_timetable_subject_imports' => "app/private/{$schoolId}/student-timetable-subjects/{$schoolyearId}/",
            'timetable_imports' => "app/private/{$schoolId}/timetable-imports/{$schoolyearId}/",
        ];

        foreach ($storageFileSources as $table => $expectedPrefix) {
            $files = $files->merge(DB::table($table)
                ->where('school_id', $schoolId)
                ->where('schoolyear_id', $schoolyearId)
                ->where('file_path', 'like', $expectedPrefix.'%')
                ->pluck('file_path')
                ->map(fn (string $path): array => ['disk' => '@storage', 'path' => $path]));
        }

        return $files->merge(DB::table('teaching_backups')
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->get(['disk', 'path'])
            ->map(fn (object $backup): array => [
                'disk' => (string) $backup->disk,
                'path' => (string) $backup->path,
            ]))
            ->filter(fn (array $file): bool => $file['path'] !== '')
            ->unique(fn (array $file): string => "{$file['disk']}:{$file['path']}")
            ->values()
            ->all();
    }

    /**
     * @param  list<array{disk:string,path:string}>  $files
     */
    private function deleteFiles(array $files): void
    {
        collect($files)
            ->groupBy('disk')
            ->each(function (Collection $diskFiles, string $disk): void {
                if ($disk === '@storage') {
                    File::delete($diskFiles->pluck('path')->map(fn (string $path): string => storage_path($path))->all());

                    return;
                }

                Storage::disk($disk)->delete($diskFiles->pluck('path')->all());
            });
    }

    /** @return list<int> */
    private function idsForScope(string $table, int $schoolId, int $schoolyearId): array
    {
        return DB::table($table)
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function idsFromTable(string $table, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $ids)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param list<int> $ids */
    private function deleteWhereIn(string $table, string $column, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->delete();
    }

    private function testUserEmail(int $schoolId, int $sourceImportId): string
    {
        return "teaching-test-2627-s{$schoolId}-i{$sourceImportId}@schooltool.invalid";
    }

    private function testUserEmailLikePattern(int $schoolId): string
    {
        return "teaching-test-2627-s{$schoolId}-i%@schooltool.invalid";
    }
}
