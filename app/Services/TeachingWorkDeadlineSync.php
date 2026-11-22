<?php

namespace App\Services;

use App\Jobs\FinalizeMultipleChoiceTest;
use App\Models\DropboxConnection;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TeachingWorkDeadlineSync
{
    public function __construct(private DropboxWorkImport $dropbox) {}

    /** Save under the caller's work row lock. */
    public function saveWorkWithDeadline(TeachingCourseWork $work, array $attributes, bool $manuallySaved = true, ?array $expected = null): void
    {
        $current = $work->finalDeadline();
        abort_unless($expected === null || ($expected['date'] === $current['date'] && $expected['time'] === $current['time']),
            409, 'Die Abgabefrist wurde inzwischen geändert. Bitte die Arbeit erneut öffnen.');
        $previous = $work->finalDeadlineInstant();
        if (isset($attributes['status'])) {
            foreach (['student_distribution', 'student_task_files', 'multiple_choice_tests', 'communicated_deadline_at',
                'communicated_deadline_date', 'final_deadline_saved', 'final_deadline_sync_pending', ...TeachingCourseWork::ManagedWorkflowFields] as $key) {
                if (array_key_exists($key, $work->status ?? [])) {
                    $attributes['status'][$key] = $work->status[$key];
                } else {
                    unset($attributes['status'][$key]);
                }
            }
        }
        $work->fill($attributes);
        $changed = $work->isDirty(['finish_until_date', 'finish_until_time']);
        if ($changed) {
            $this->validate($work);
            $this->updateMcDeadlines($work, $previous);
            if ($manuallySaved) {
                $work->status = array_merge($work->status ?? [], ['final_deadline_saved' => true, 'final_deadline_sync_pending' => true]);
            }
        }
        $work->save();
        if ($changed) {
            app(TeachingWorkStudentDistribution::class)->signalDeadlineChangeAfterCommit($work);
        }
    }

    public function validate(TeachingCourseWork $work): void
    {
        if ($work->finish_until_date === null && $work->finish_until_time) {
            throw ValidationException::withMessages(['finish_until_date' => 'Zur Uhrzeit fehlt das Datum der endgültigen Abgabefrist.']);
        }
        if ($work->finish_until_date !== null && $work->finalDeadlineInstant() === null) {
            throw ValidationException::withMessages(['finish_until_time' => 'Die endgültige Abgabefrist ist keine gültige Wiener Ortszeit.']);
        }
    }

    private function updateMcDeadlines(TeachingCourseWork $work, ?CarbonImmutable $previous): void
    {
        $ids = collect($work->status['multiple_choice_tests'] ?? [])
            ->filter(fn ($binding) => is_array($binding) && (int) ($binding['work_id'] ?? 0) === $work->id)
            ->pluck('test_id')->filter(fn ($id) => is_numeric($id) && (int) $id > 0)->unique()->values();
        if ($work->studentReleaseEnded() || $ids->isEmpty() || ! Schema::hasTable('mc_tests')) {
            return;
        }
        $course = $work->teachingCourse;
        $deadline = $work->finalDeadlineInstant();
        $tests = McTest::query()->whereIn('id', $ids)->where('school_id', $course->school_id)
            ->where('schoolyear_id', $course->schoolyear_id)->where('owner_id', $course->user_id)
            ->whereIn('status', ['draft', 'published', 'withdrawn'])
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($tests as $test) {
            $otherWorks = $test->deadlineWorks()->reject(fn (TeachingCourseWork $linked): bool => $linked->is($work));
            if ($otherWorks->isNotEmpty() && $deadline?->getTimestamp() !== $previous?->getTimestamp()) {
                abort_if($otherWorks->contains(fn (TeachingCourseWork $linked): bool => $linked->finalDeadlineInstant()?->getTimestamp() !== $deadline?->getTimestamp()),
                    409, 'Der MC-Test ist mehreren Arbeiten zugeordnet. Die Abgabefrist kann nur geändert werden, wenn sie danach für alle verknüpften Arbeiten übereinstimmt.');
            }
            if ($test->status === 'published' && $deadline !== null && ($test->starts_at !== null
                || $test->attempts()->whereNotNull('started_at')->whereNull('completed_at')->exists())) {
                FinalizeMultipleChoiceTest::dispatch((int) $test->id)->delay($deadline->isPast() ? now() : $deadline)->afterCommit();
            }
            if ($test->start_mode !== 'individual' || $test->status === 'withdrawn'
                || app(TeachingWorkStudentDistribution::class)->mcTestShared($work, $test)) {
                continue;
            }
            $inherited = $test->snapshot['work_deadline_inherited'] ?? null;
            $origin = $test->snapshot['schooltool_work'] ?? [];
            $binding = $work->status['multiple_choice_tests'][$origin['slot'] ?? ''] ?? [];
            $provenOrigin = ($origin['work_id'] ?? null) === $work->id
                && ($origin['course_id'] ?? null) === $work->teaching_course_id && (int) ($binding['test_id'] ?? 0) === $test->id;
            $managedLink = collect($work->status['multiple_choice_tests'] ?? [])->contains(fn ($item) => is_array($item)
                && (int) ($item['work_id'] ?? 0) === $work->id && (int) ($item['test_id'] ?? 0) === $test->id
                && isset($item['request_id'], $item['request_hash'], $item['points_plan']));
            $matchesPrevious = $test->closes_at === null ? $test->status === 'draft'
                : $previous !== null && $test->closes_at->equalTo($previous);
            if ($inherited !== true && ($inherited === false || ! $matchesPrevious || (! $provenOrigin && ! $managedLink))) {
                continue;
            }
            if ($test->status === 'published' && $deadline === null) {
                throw ValidationException::withMessages(['finish_until_date' => 'Der freigegebene individuelle MC-Test benötigt eine endgültige Abgabefrist.']);
            }
            $snapshot = $test->snapshot;
            if ($snapshot !== null) {
                $snapshot['work_deadline_inherited'] = true;
                $snapshot['closes_at'] = $deadline?->toIso8601String();
            }
            McTest::query()->whereKey($test->id)->update(['closes_at' => $deadline,
                'snapshot' => $snapshot === null ? null : json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        }
    }

    public function syncLinkedFolders(TeachingCourseWork $saved, User $actor): void
    {
        $modules = app(DropboxModuleLinks::class);
        $link = $modules->current($saved);
        if (! $link) {
            $this->markFolderDeadlineSynced($saved);

            return;
        }
        $connection = DropboxConnection::query()->where('user_id', $actor->id)->whereNull('revoked_at')->find($link->dropbox_connection_id);
        abort_unless($connection && $connection->account_id === $link->account_id, 409,
            'Dropbox konnte für die verknüpfte Leistungsfeststellung nicht bestätigt werden.');
        DB::transaction(function () use ($saved, $modules, $connection, $link): void {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($saved->id);
            if ($work->finalDeadline() !== $saved->finalDeadline()) {
                return;
            }
            abort_unless($modules->current($work)?->getAttributes() === $link->getAttributes(), 409,
                'Die Modulzuordnung wurde während der Aktualisierung geändert.');
            foreach ($modules->exerciseFoldersForDeadlineSync($connection, $work, $link) as $folder) {
                $this->sync($connection, $work, $folder, allowMissing: true);
            }
            $this->markFolderDeadlineSynced($work);
        });
        $modules->forgetExerciseCache($saved, $actor);
    }

    private function markFolderDeadlineSynced(TeachingCourseWork $work): void
    {
        TeachingCourseWork::query()->whereKey($work->id)
            ->where('finish_until_date', $work->finalDeadline()['date'])
            ->where('finish_until_time', $work->finalDeadline()['time'])
            ->update(['status' => DB::raw("JSON_REMOVE(status, '$.final_deadline_sync_pending')")]);
    }

    /** @param array<string, mixed> $folder
     * @return array{changed: bool, message: string}
     */
    public function sync(DropboxConnection $connection, TeachingCourseWork $work, array $folder, bool $allowMissing = false): array
    {
        $parent = $this->dropbox->metadata($connection, $folder['id']);
        $path = $parent['path_display'] ?? $parent['path_lower'];
        abort_unless($parent['id'] === $folder['id'] && $path === $folder['path'], 409, 'Der Leistungsfeststellungsordner wurde inzwischen verschoben.');
        $document = $this->read($connection, $path.'/Anlage_und_Quellen.json');
        if ($document === null && $allowMissing) {
            return ['changed' => false, 'message' => 'Der Prüfungsordner enthält keine synchronisierbaren Fristmetadaten.'];
        }
        abort_unless($document !== null, 409, 'Die Fristmetadaten fehlen.');
        $metadata = $document['value'];
        abort_unless(($metadata['Schooltool_Arbeit_ID'] ?? null) === $work->id
            && ($metadata['Schooltool_Kurs_ID'] ?? null) === $work->teaching_course_id
            && ($metadata['Zeitzone'] ?? null) === 'Europe/Vienna', 409, 'Die Fristmetadaten gehören nicht zu dieser Arbeit.');
        $updated = $this->metadata($work, $metadata);
        $order = $this->read($connection, $path.'/E-Mail-Vorbereitung/Auftragsvorbereitung.json');
        $contents = $document['contents'];
        $writes = [];
        if ($updated !== $metadata) {
            $updated['FristSynchronisierung'] = ['at' => now()->toIso8601String(), 'source' => 'Schooltool'];
            $contents = $this->encode($updated);
            $writes[] = [$document, $contents];
        }
        if ($order !== null) {
            $value = $order['value'];
            abort_unless(($value['Leistungsfeststellung'] ?? null) === ($metadata['Leistungsfeststellungsordner'] ?? null), 409,
                'Die E-Mail-Vorbereitung gehört zu einem anderen Ordner.');
            $source = ['Datei' => 'Anlage_und_Quellen.json', 'SHA256' => hash('sha256', $contents)];
            if (($value['Fristquelle'] ?? null) !== $source) {
                $value['Fristquelle'] = $source;
                $writes[] = [$order, $this->encode($value)];
            }
        }
        if ($writes === []) {
            return ['changed' => false, 'message' => 'Die Abgabefrist ist mit Dropbox abgeglichen.'];
        }
        abort_unless($this->dropbox->canWrite($connection), 422, 'Dropbox benötigt die Schreibfreigabe für die Abgabefrist.');
        foreach ($writes as [$file, $bytes]) {
            $this->write($connection, $file, $bytes);
        }

        return ['changed' => true, 'message' => 'Die Abgabefrist wurde in den aktuellen Dropbox-Metadaten aktualisiert.'];
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function metadata(TeachingCourseWork $work, array $metadata): array
    {
        $this->validate($work);
        $fields = $work->finalDeadline();
        $metadata['Abgabeschluss'] = $fields['time'] === null ? null : $work->finalDeadlineInstant()?->toIso8601String();
        $metadata['Schooltool_Fristdaten']['main_deadline'] = $fields;
        unset($metadata['MitgeteilteAbgabefrist'], $metadata['Schooltool_Fristdaten']['communicated_deadline'], $metadata['Fristhinweis']);

        return $metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function matchesFinalDeadline(TeachingCourseWork $work, array $metadata): bool
    {
        if (isset($metadata['Schooltool_Fristdaten']['main_deadline'])) {
            return $metadata['Schooltool_Fristdaten']['main_deadline'] === $work->finalDeadline();
        }
        $expected = $work->finish_until_time ? $work->finalDeadlineInstant() : null;
        $source = $metadata['Abgabeschluss'] ?? null;
        if ($expected === null || $source === null) {
            return $expected === null && $source === null && $work->finish_until_date === null;
        }
        $instant = is_string($source) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00(?:Z|[+-]\d{2}:\d{2})\z/', $source)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $source)) : false;

        return $instant !== false && $expected->equalTo($instant);
    }

    /** @return array<string, mixed>|null */
    private function read(DropboxConnection $connection, string $path): ?array
    {
        $entry = $this->dropbox->findModuleEntry($connection, $path);
        if ($entry === null) {
            return null;
        }
        abort_unless(($entry['.tag'] ?? null) === 'file' && ($entry['size'] ?? PHP_INT_MAX) <= 1024 * 1024
            && ($entry['path_display'] ?? null) === $path && is_string($entry['rev'] ?? null), 422, 'Ungültige Fristdatei.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        abort_unless(strlen($contents) === $entry['size'], 422, 'Die Fristdatei wurde nicht vollständig gelesen.');
        $value = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_array($value) && ! array_is_list($value), 422, 'Ungültige Fristmetadaten.');

        return ['entry' => $entry, 'contents' => $contents, 'value' => $value];
    }

    /** @param array<string, mixed> $document */
    private function write(DropboxConnection $connection, array $document, string $contents): void
    {
        $entry = $document['entry'];
        $this->dropbox->replaceModuleTemplate($connection, $entry['id'], $entry['rev'], $contents, $entry['path_display']);
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
