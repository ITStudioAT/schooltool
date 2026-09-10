<?php

namespace App\Services;

use App\Models\PersonalTeachingBackup;
use App\Models\User;
use App\Services\Materials\MaterialService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PersonalTeachingBackupService
{
    public const MAX_PAYLOAD_BYTES = 50 * 1024 * 1024;

    private const MAX_FILE_BYTES = 25 * 1024 * 1024;

    /** @var array<string, bool> */
    private array $sharedReferenceChecks = [];

    private const SETTINGS = [
        'teaching_active_semester', 'teaching_count_for_semester_2_date',
        'teaching_behaviour', 'teaching_behaviour_by_schoolyear',
        'teaching_notifications', 'teaching_notifications_by_schoolyear',
        'teaching_show_behaviour', 'teaching_grade_columns_by_schoolyear',
        'teaching_student_grade_columns_by_schoolyear',
    ];

    private const ROOTS = [
        'teaching_entry_areas', 'teaching_entry_grading_parts', 'teaching_entry_definitions',
        'teaching_schemas', 'teaching_curricula', 'teaching_imported_curricula',
        'teaching_class_head_emails', 'teaching_holidays', 'teaching_courses',
    ];

    /** @var array<string, array{string, string}> */
    private const CHILDREN = [
        'teaching_curriculum_documents' => ['teaching_curriculum_id', 'teaching_curricula'],
        'teaching_course_dates' => ['teaching_course_id', 'teaching_courses'],
        'teaching_course_students' => ['teaching_course_id', 'teaching_courses'],
        'teaching_course_works' => ['teaching_course_id', 'teaching_courses'],
        'teaching_course_behaviour_entries' => ['teaching_course_id', 'teaching_courses'],
        'teaching_course_student_category_evaluations' => ['teaching_course_id', 'teaching_courses'],
        'teaching_course_student_entries' => ['teaching_course_id', 'teaching_courses'],
        'teaching_course_work_group_students' => ['teaching_course_work_id', 'teaching_course_works'],
        'teaching_course_student_entry_notifications' => ['teaching_course_student_entry_id', 'teaching_course_student_entries'],
        'teaching_course_date_materials' => ['teaching_course_date_id', 'teaching_course_dates'],
        'teaching_course_date_material_attachments' => ['teaching_course_date_material_id', 'teaching_course_date_materials'],
        'user_groups' => ['teaching_course_id', 'teaching_courses'],
        'user_group_members' => ['user_group_id', 'user_groups'],
    ];

    /** @var array<string, array<string, string>> */
    private const REFERENCES = [
        'teaching_entry_grading_parts' => ['teaching_entry_area_id' => 'teaching_entry_areas'],
        'teaching_entry_definitions' => ['teaching_entry_area_id' => 'teaching_entry_areas', 'teaching_entry_grading_part_id' => 'teaching_entry_grading_parts'],
        'teaching_courses' => ['teaching_entry_area_id' => 'teaching_entry_areas', 'teaching_curriculum_id' => 'teaching_curricula'],
        'teaching_imported_curricula' => ['adopted_curriculum_id' => 'teaching_curricula'],
        'teaching_course_student_entries' => ['teaching_course_work_id' => 'teaching_course_works'],
        'teaching_course_work_group_students' => ['teaching_course_id' => 'teaching_courses'],
        'teaching_course_date_material_attachments' => ['source_teaching_curriculum_document_id' => 'teaching_curriculum_documents'],
    ];

    public function __construct(private PersonalTeachingBackupRecoveryService $recovery) {}

    public function create(User $user): PersonalTeachingBackup
    {
        return DB::transaction(function () use ($user): PersonalTeachingBackup {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $payload = $this->snapshot($user);

            return PersonalTeachingBackup::query()->create([
                'user_id' => $user->id,
                'school_id' => $user->school_id,
                'payload' => $payload,
                'summary' => [
                    'courses' => count($payload['tables']['teaching_courses']),
                    'students' => count($payload['tables']['teaching_course_students']),
                    'curricula' => count($payload['tables']['teaching_curricula']),
                    'records' => array_sum(array_map('count', $payload['tables'])),
                    'files' => count($payload['files']),
                    'schoolyears' => collect($payload['tables'])->flatten(1)->pluck('schoolyear_id')->filter()->unique()->count(),
                ],
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(User $user): array
    {
        abort_unless($user->school_id, 403);
        $tables = $this->currentTables($user);
        $files = [];
        $fileBytes = 0;
        foreach ($this->fileReferences($tables) as $file) {
            $content = $this->readFile($file, $fileBytes);
            $files[] = array_diff_key($file, ['path' => true]) + [
                'content' => base64_encode($content),
                'sha256' => hash('sha256', $content),
            ];
        }

        $payload = [
            'version' => 1,
            'user_id' => (int) $user->id,
            'school_id' => (int) $user->school_id,
            'settings' => (array) DB::table('users')->where('id', $user->id)->first(self::SETTINGS),
            'tables' => $tables,
            'files' => $files,
            'identities' => $this->identities($user, $tables),
        ];
        if (strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > self::MAX_PAYLOAD_BYTES) {
            $this->invalid('Die Unterrichtsdaten überschreiten die Sicherungsgrenze von 50 MiB. Es wurde keine unvollständige Sicherung angelegt.');
        }
        $this->validatePayload($user, $payload, $tables);

        return $payload;
    }

    /** @param array<string, array<int, array<string, mixed>>> $tables
     * @return array{users:array,imports:array}
     */
    private function identities(User $user, array $tables): array
    {
        $userIds = [];
        $importIds = [];
        foreach (self::CHILDREN as $table => $relationship) {
            foreach ($tables[$table] as $row) {
                foreach (['user_id', 'linked_user_id'] as $column) {
                    if (isset($row[$column])) {
                        $userIds[] = $row[$column];
                    }
                }
                if (isset($row['import116_id'])) {
                    $importIds[] = $row['import116_id'];
                }
            }
        }

        return [
            'users' => DB::table('users')->where('school_id', $user->school_id)->whereIn('id', array_unique($userIds))
                ->orderBy('id')->get(['id', 'school_id', 'last_name', 'first_name', 'email', 'schoolclass'])->map(fn (object $row): array => (array) $row)->all(),
            'imports' => DB::table('import116')->where('school_id', $user->school_id)->whereIn('id', array_unique($importIds))
                ->orderBy('id')->get(['id', 'school_id', 'schoolyear_id', 'student_code', 'last_name', 'first_name', 'class', 'email'])->map(fn (object $row): array => (array) $row)->all(),
        ];
    }

    public function restore(User $user, PersonalTeachingBackup $backup): void
    {
        abort_unless((int) $backup->user_id === (int) $user->id && (int) $backup->school_id === (int) $user->school_id, 404);
        $createdPaths = [];
        try {
            DB::transaction(function () use ($user, $backup, &$createdPaths): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                abort_unless((int) $lockedUser->school_id === (int) $backup->school_id, 404);
                $freshBackup = $backup->fresh();
                $payload = $this->recovery->applyMappings($lockedUser, $freshBackup, $freshBackup->payload);
                $current = $this->currentTables($lockedUser, lock: true);
                $current = $this->includeOrphanedSnapshotRows($lockedUser, $payload, $current);
                $this->validatePayload($lockedUser, $payload, $current);
                $tables = $payload['tables'];
                $this->assertNoExternalDependents($current, $tables);
                $this->restoreFiles($tables, $payload['files'], $createdPaths);
                $exportKeys = DB::table('teaching_curricula')->whereIn('id', array_column($current['teaching_curricula'], 'id'))->pluck('export_key', 'id');
                foreach (array_reverse(array_keys($current)) as $table) {
                    DB::table($table)->whereIn('id', array_column($current[$table], 'id'))->delete();
                }
                foreach ($tables as $table => $rows) {
                    foreach ($rows as $row) {
                        if ($table === 'teaching_curricula') {
                            $row['export_key'] = $exportKeys[$row['id']] ?? (string) Str::uuid();
                        }
                        DB::table($table)->insert($row);
                    }
                }
                DB::table('users')->where('id', $lockedUser->id)->update($payload['settings']);
                PersonalTeachingBackup::query()->whereKey($freshBackup->id)->update(['recovery_requested_at' => null]);
            });
        } catch (Throwable $exception) {
            if ($createdPaths !== []) {
                Storage::disk('local')->delete($createdPaths);
            }
            throw $exception;
        }
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function currentTables(User $user, bool $lock = false): array
    {
        $tables = [];
        foreach (self::ROOTS as $table) {
            $query = DB::table($table)->where('user_id', $user->id)->where('school_id', $user->school_id);
            if ($table === 'teaching_holidays') {
                $query->where('scope', 'teacher');
            }
            if ($lock) {
                $query->lockForUpdate();
            }
            $tables[$table] = $query->orderBy('id')->get()->map(function (object $row) use ($table): array {
                $attributes = (array) $row;
                if ($table === 'teaching_curricula') {
                    unset($attributes['export_key']);
                }

                return $attributes;
            })->all();
        }
        foreach (self::CHILDREN as $table => [$column, $parent]) {
            $query = DB::table($table)->whereIn($column, array_column($tables[$parent], 'id'));
            if ($table === 'user_groups') {
                $query->where('school_id', $user->school_id)->where('created_by_user_id', $user->id);
            }
            if ($lock) {
                $query->lockForUpdate();
            }
            $tables[$table] = $query->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $tables;
    }

    /**
     * Legacy course deletion leaves children without foreign key constraints behind.
     * Only reclaim snapshot IDs whose parent identity still matches the authenticated snapshot.
     * Root ID collisions are rejected separately before any mutation.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<int, array<string, mixed>>>  $current
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function includeOrphanedSnapshotRows(User $user, array $payload, array $current): array
    {
        if (! is_array($payload['tables'] ?? null)) {
            $this->invalid();
        }
        foreach ($payload['tables'] as $rows) {
            if (! is_array($rows) || ! array_is_list($rows) || collect($rows)->contains(fn (mixed $row): bool => ! is_array($row))) {
                $this->invalid();
            }
        }
        foreach (self::CHILDREN as $table => [$column, $parent]) {
            foreach ($payload['tables'][$table] ?? [] as $saved) {
                if (! is_array($saved) || ! isset($saved['id'], $saved[$column]) || in_array($saved['id'], array_column($current[$table], 'id'))) {
                    continue;
                }
                $row = DB::table($table)->where('id', $saved['id'])->where($column, $saved[$column])->lockForUpdate()->first();
                if (! $row || ! in_array($saved[$column], array_column($payload['tables'][$parent] ?? [], 'id'))) {
                    continue;
                }
                if ($table === 'user_groups' && ((int) $row->school_id !== (int) $user->school_id || (int) $row->created_by_user_id !== (int) $user->id)) {
                    continue;
                }
                $current[$table][] = (array) $row;
            }
        }

        return $current;
    }

    /** @param array<string, mixed> $payload
     * @param  array<string, array<int, array<string, mixed>>>  $current
     */
    private function validatePayload(User $user, array $payload, array $current): void
    {
        $this->sharedReferenceChecks = [];
        if (($payload['version'] ?? null) !== 1 || ($payload['user_id'] ?? null) !== (int) $user->id || ($payload['school_id'] ?? null) !== (int) $user->school_id
            || ! is_array($payload['tables'] ?? null) || array_keys($payload['tables']) !== array_keys($current)
            || ! is_array($payload['settings'] ?? null) || array_diff(array_keys($payload['settings']), self::SETTINGS) !== [] || count($payload['settings']) !== count(self::SETTINGS)
            || ! is_array($payload['files'] ?? null)) {
            $this->invalid();
        }
        $tables = $payload['tables'];
        foreach ($tables as $table => $rows) {
            if (! is_array($rows) || ! array_is_list($rows)) {
                $this->invalid();
            }
            $ids = [];
            $columns = Schema::getColumnListing($table);
            foreach ($rows as $row) {
                if (! is_array($row) || ! is_numeric($row['id'] ?? null) || (int) $row['id'] <= 0 || in_array($row['id'], $ids)
                    || array_diff(array_keys($row), $columns) !== [] || array_key_exists('export_key', $row)) {
                    $this->invalid();
                }
                $ids[] = $row['id'];
                if (in_array($table, self::ROOTS, true) && ((int) ($row['user_id'] ?? 0) !== (int) $user->id || (int) ($row['school_id'] ?? 0) !== (int) $user->school_id)) {
                    $this->invalid();
                }
                if ($table === 'teaching_holidays' && ($row['scope'] ?? null) !== 'teacher') {
                    $this->invalid();
                }
                if ($table === 'user_groups' && ((int) ($row['school_id'] ?? 0) !== (int) $user->school_id || (int) ($row['created_by_user_id'] ?? 0) !== (int) $user->id)) {
                    $this->invalid();
                }
                if (isset(self::CHILDREN[$table])) {
                    [$column, $parent] = self::CHILDREN[$table];
                    if (! in_array($row[$column] ?? null, array_column($tables[$parent], 'id'))) {
                        $this->invalid();
                    }
                }
                foreach (self::REFERENCES[$table] ?? [] as $column => $parent) {
                    if (isset($row[$column]) && ! in_array($row[$column], array_column($tables[$parent], 'id'))) {
                        $this->invalid('Eine zugehörige persönliche Unterrichtseinstellung fehlt in der Sicherung.');
                    }
                }
                $this->validateSharedReferences($user, $table, $row, $tables);
            }
            if (DB::table($table)->whereIn('id', array_diff($ids, array_column($current[$table], 'id')))->exists()) {
                $this->invalid('Eine gespeicherte Kennung gehört inzwischen zu anderen Daten. Es wurde nichts wiederhergestellt.');
            }
        }
        $expectedFiles = $this->fileReferences($tables);
        if (count($expectedFiles) !== count($payload['files'])) {
            $this->invalid('Die Sicherung enthält nicht alle benötigten Dateien.');
        }
        foreach ($expectedFiles as $index => $expected) {
            $file = $payload['files'][$index] ?? [];
            $content = is_string($file['content'] ?? null) ? base64_decode($file['content'], true) : false;
            if ($content === false || ! hash_equals(hash('sha256', $content), (string) ($file['sha256'] ?? ''))
                || array_intersect_key($file, array_flip(['table', 'id', 'column', 'disk'])) !== array_diff_key($expected, ['path' => true])) {
                $this->invalid('Die Sicherung enthält eine unvollständige oder beschädigte Datei.');
            }
        }
    }

    /** @param array<string, mixed> $row
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     */
    private function validateSharedReferences(User $user, string $table, array $row, array $tables): void
    {
        foreach (['schoolyear_id' => 'schoolyears', 'source_schoolyear_id' => 'schoolyears', 'import116_id' => 'import116'] as $column => $target) {
            if (isset($row[$column]) && ! $this->rememberReference("{$target}:{$user->school_id}:{$row[$column]}", fn (): bool => DB::table($target)->where('id', $row[$column])->where('school_id', $user->school_id)->sharedLock()->first(['id']) !== null)) {
                $this->invalid('Ein benötigtes Schuljahr oder ein Schülerimport fehlt.');
            }
        }
        $userColumns = in_array($table, self::ROOTS, true) ? [] : ['user_id', 'linked_user_id', 'added_by_user_id', 'confirmed_by_user_id'];
        foreach ($userColumns as $column) {
            if (isset($row[$column]) && ! $this->rememberReference("users:{$user->school_id}:{$row[$column]}", fn (): bool => DB::table('users')->where('id', $row[$column])->where('school_id', $user->school_id)->sharedLock()->first(['id']) !== null)) {
                $this->invalid('Ein benötigtes Benutzer- oder Schülerkonto fehlt. Konten werden durch diese Sicherung nicht verändert.');
            }
        }
        if (isset($row['material_card_id']) && ! $this->rememberReference("material:{$user->id}:{$row['material_card_id']}", fn (): bool => app(MaterialService::class)->loadMaterialCardForCurriculumUse($user, (int) $row['material_card_id']) !== null)) {
            $this->invalid('Ein verknüpftes Material fehlt oder ist nicht mehr freigegeben.');
        }
        if (isset($row['material_card_attachment_id']) && (! isset($row['material_card_id']) || ! $this->rememberReference("attachment:{$row['material_card_id']}:{$row['material_card_attachment_id']}", fn (): bool => DB::table('material_card_attachments')->where('id', $row['material_card_attachment_id'])->where('material_card_id', $row['material_card_id'])->exists()))) {
            $this->invalid('Ein verknüpfter Materialanhang ist nicht mehr vorhanden.');
        }
        if ($table === 'teaching_courses' && isset($row['teaching_schema_id'])) {
            $ownSchema = collect($tables['teaching_schemas'])->contains(fn (array $schema): bool => $schema['schema_id'] === $row['teaching_schema_id'] && $schema['schoolyear_id'] == $row['schoolyear_id']);
            if (! $ownSchema && ! $this->rememberReference("schema:{$user->school_id}:{$row['teaching_schema_id']}", fn (): bool => DB::table('teaching_schemas')->where('schema_id', $row['teaching_schema_id'])->whereNull('user_id')->where(fn ($query) => $query->whereNull('school_id')->orWhere('school_id', $user->school_id))->exists())) {
                $this->invalid('Ein benötigtes Bewertungsschema fehlt.');
            }
        }
    }

    private function rememberReference(string $key, Closure $check): bool
    {
        return $this->sharedReferenceChecks[$key] ??= $check();
    }

    /** @param array<string, array<int, array<string, mixed>>> $current
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     */
    private function assertNoExternalDependents(array $current, array $tables): void
    {
        $edges = self::REFERENCES;
        foreach (self::CHILDREN as $table => [$column, $parent]) {
            $edges[$table][$column] = $parent;
        }
        $edges['material_share_targets']['user_group_id'] = 'user_groups';
        foreach ($edges as $dependent => $references) {
            foreach ($references as $column => $parent) {
                $affected = array_unique(array_merge(array_column($current[$parent], 'id'), array_column($tables[$parent], 'id')));
                if (DB::table($dependent)->whereIn($column, $affected)->whereNotIn('id', array_column($current[$dependent] ?? [], 'id'))->lockForUpdate()->first(['id'])) {
                    $this->invalid('Andere Daten oder Materialfreigaben verwenden einen zu entfernenden Datensatz. Die Wiederherstellung wurde abgebrochen.');
                }
            }
        }
    }

    /** @param array<string, array<int, array<string, mixed>>> $tables
     * @return array<int, array{table:string,id:int,column:string,disk:string,path:string}>
     */
    private function fileReferences(array $tables): array
    {
        $files = [];
        foreach (['teaching_curriculum_documents', 'teaching_course_date_material_attachments', 'teaching_imported_curricula'] as $table) {
            foreach ($tables[$table] as $row) {
                $column = 'file_path';
                $disk = (string) ($row['storage_disk'] ?? '');
                $path = $row['file_path'] ?? null;
                if ($table === 'teaching_curriculum_documents' && ! in_array($row['source_type'], ['upload', 'unit_file'], true)) {
                    continue;
                }
                if ($table === 'teaching_imported_curricula') {
                    $materials = json_decode($row['materials'] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                    $path = $materials['archive_path'] ?? null;
                    $column = 'materials.archive_path';
                    $disk = 'local';
                    if ($path !== null && ! preg_match('~^teaching/imported_curricula/[a-f0-9-]{36}\.zip$~D', $path)) {
                        $this->invalid('Ein Curriculum-Archiv hat einen ungültigen Pfad.');
                    }
                }
                if (! is_string($path) || $path === '') {
                    continue;
                }
                if ($table === 'teaching_curriculum_documents') {
                    $row['school_id'] = collect($tables['teaching_curricula'])->firstWhere('id', $row['teaching_curriculum_id'])['school_id'];
                }
                $this->assertSafeFilePath($table, $row, $path, $disk);
                $files[] = ['table' => $table, 'id' => (int) $row['id'], 'column' => $column, 'disk' => $disk, 'path' => $path];
            }
        }

        return $files;
    }

    /** @param array<string, mixed> $row */
    private function assertSafeFilePath(string $table, array $row, string $path, string $disk): void
    {
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, ':') || str_contains($path, "\0") || str_starts_with($path, '/')) {
            $this->invalid('Eine Unterrichtsdatei hat einen ungültigen Pfad.');
        }
        $relative = $disk === '' && str_starts_with($path, 'app/private/') ? substr($path, 12) : $path;
        $allowed = match ($table) {
            'teaching_course_date_material_attachments' => ['teaching/course_date_materials/'.$row['teaching_course_date_material_id'].'/', 'teaching/personal_restores/'],
            'teaching_curriculum_documents' => [$row['school_id'].'/curricula/'.$row['teaching_curriculum_id'].'/', 'teaching/curriculum_unit_files/'.$row['teaching_curriculum_id'].'/', 'teaching/curriculum_imports/', 'teaching/personal_restores/'],
            default => ['teaching/imported_curricula/'],
        };
        if (! collect($allowed)->contains(fn (string $prefix): bool => str_starts_with($relative, $prefix))) {
            $this->invalid('Eine Unterrichtsdatei liegt außerhalb des zulässigen Speicherbereichs.');
        }
    }

    /** @param array{table:string,id:int,column:string,disk:string,path:string} $file */
    private function readFile(array $file, int &$fileBytes): string
    {
        $path = $file['path'];
        if ($file['disk'] === '' && str_starts_with($path, 'app/private/')) {
            $path = substr($path, 12);
        }
        $disks = $file['disk'] !== '' ? [$file['disk']] : array_unique([config('filesystems.default'), 'local', 's3']);
        foreach ($disks as $disk) {
            if (! is_string($disk) || ! is_array(config("filesystems.disks.{$disk}"))) {
                continue;
            }
            try {
                if (Storage::disk($disk)->exists($path)) {
                    if ($fileBytes + Storage::disk($disk)->size($path) > self::MAX_FILE_BYTES) {
                        $this->invalid('Die Unterrichtsdateien überschreiten zusammen die Sicherungsgrenze von 25 MiB. Es wurde keine unvollständige Sicherung angelegt.');
                    }
                    $content = Storage::disk($disk)->get($path);
                    if (is_string($content)) {
                        $fileBytes += strlen($content);
                        if ($fileBytes > self::MAX_FILE_BYTES) {
                            $this->invalid('Die Unterrichtsdateien überschreiten zusammen die Sicherungsgrenze von 25 MiB.');
                        }

                        return $content;
                    }
                }
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable) {
                continue;
            }
        }
        $this->invalid('Eine Unterrichtsdatei fehlt oder kann nicht gelesen werden. Es wurde keine unvollständige Sicherung angelegt.');
    }

    /** @param array<string, array<int, array<string, mixed>>> $tables
     * @param  array<int, array<string, mixed>>  $files
     * @param  array<int, string>  $createdPaths
     */
    private function restoreFiles(array &$tables, array $files, array &$createdPaths): void
    {
        foreach ($files as $file) {
            $path = $file['column'] === 'materials.archive_path'
                ? 'teaching/imported_curricula/'.Str::uuid().'.zip'
                : 'teaching/personal_restores/'.Str::uuid();
            $createdPaths[] = $path;
            if (! Storage::disk('local')->put($path, base64_decode($file['content'], true), ['visibility' => 'private'])) {
                $this->invalid('Eine Datei konnte nicht wiederhergestellt werden.');
            }
            foreach ($tables[$file['table']] as &$row) {
                if ((int) $row['id'] !== $file['id']) {
                    continue;
                }
                if ($file['column'] === 'materials.archive_path') {
                    $materials = json_decode($row['materials'], true, 512, JSON_THROW_ON_ERROR);
                    $materials['archive_path'] = $path;
                    $row['materials'] = json_encode($materials, JSON_THROW_ON_ERROR);
                } else {
                    $row['file_path'] = $path;
                    if ($file['table'] === 'teaching_curriculum_documents') {
                        $row['storage_disk'] = 'local';
                    }
                }
            }
            unset($row);
        }
    }

    private function invalid(string $message = 'Die Sicherung ist ungültig oder gehört nicht zu diesem Benutzer und dieser Schule.'): never
    {
        throw ValidationException::withMessages(['backup' => $message]);
    }
}
