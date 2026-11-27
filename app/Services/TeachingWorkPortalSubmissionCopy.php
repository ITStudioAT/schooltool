<?php

namespace App\Services;

use App\Exceptions\DropboxExerciseRequestFailed;
use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Support\SchooltoolAssessmentJson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TeachingWorkPortalSubmissionCopy
{
    public function __construct(
        private DropboxWorkImport $dropbox,
        private DropboxModuleLinks $modules,
        private TeachingWorkPortalReopening $reopening,
        private TeachingWorkAiExport $export,
        private TeachingDirectAssessment $assessment,
    ) {}

    /** @return array<string, mixed> */
    public function prepare(TeachingCourseWork $work, User $actor, ?string $date): array
    {
        $selection = $this->closedSelection($work, $date);
        $date = $selection['date'];
        $eligible = array_column($selection['students'], 'id');
        $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $files = $this->activeFiles($work, $groups, $date, $eligible);
        $result = ['work_id' => $work->id, 'date' => $date, 'run_id' => null, 'files' => [],
            'candidates' => array_map(fn (array $student): array => ['id' => $student['id'], 'name' => $student['name']], $selection['students'])];
        if ($files->isEmpty()) {
            return $result;
        }

        $link = $this->targetBinding($work);
        abort_unless($link, 409, 'Für diesen Termin fehlt eine eindeutige Dropbox-Zuordnung. Bitte die Arbeit unter „Vorbereiten“ verknüpfen.');
        $connection = $this->dropbox->connection($actor);
        abort_unless($this->dropbox->canWrite($connection), 409, 'Bitte Dropbox mit Schreibzugriff verbinden.');
        $folder = $this->modules->portalSubmissionTarget($connection, $work, $link, $date);
        $candidates = $this->dropbox->findExerciseChildFolder($connection, $folder, 'Kandidaten');
        abort_unless($candidates, 409, 'Im bestätigten Prüfungsordner fehlt der Kandidatenordner.');

        $folders = $this->candidateNames($work);
        $idCounts = $files->pluck('id')->filter(fn ($id): bool => is_string($id))->countBy();
        $run = Str::uuid()->toString();
        $items = [];
        foreach ($files as $index => $file) {
            $student = (int) $file['student_id'];
            $name = $folders[$student] ?? null;
            $fileId = $file['id'] ?? '';
            $valid = in_array($student, $eligible, true) && $name !== null
                && is_string($fileId) && Str::isUuid($fileId) && $idCounts->get($fileId) === 1;
            if ($valid) {
                $items[$index] = ['id' => $fileId, 'receipt_digest' => SchooltoolAssessmentJson::digest($file),
                    'candidate_digest' => hash('sha256', $name)];
            }
            $result['files'][] = ['item' => $index, 'candidate_id' => $student, 'student_name' => $file['student_name'] ?? null,
                'name' => $file['name'] ?? 'Originaldatei',
                'message' => $valid ? null : 'Die Datei oder der persönliche Prüfungsordner ist dieser Person und diesem Termin nicht eindeutig zugeordnet.'];
        }
        if ($items !== []) {
            Cache::store('database')->put($this->key($work, $actor, $run), [
                'date' => $date, 'binding' => $this->binding($work, $link), 'folder_id' => $folder['id'], 'observed_at' => now()->toIso8601String(),
                'candidates' => $candidates, 'items' => $items,
            ], now()->addHour());
            $result['run_id'] = $run;
        }

        return $result;
    }

    /** Copy one original while holding the same work lock used by uploads and reopening. */
    public function copy(TeachingCourseWork $work, User $actor, string $run, int $index): array
    {
        $plan = Cache::store('database')->get($this->key($work, $actor, $run));
        abort_unless(is_array($plan), 409, 'Der Kopierlauf ist abgelaufen. Bitte erneut starten.');
        $group = $this->closedSelection($work, $plan['date'], lock: true);
        $selection = $plan['items'][$index] ?? null;
        abort_unless($selection, 404);
        $connection = $this->dropbox->connection($actor);
        $link = $this->targetBinding($work);
        abort_unless($link && $this->binding($work, $link) === $plan['binding']
            && $link->dropbox_connection_id === $connection->id
            && (! ($link instanceof TeachingWorkModuleLink) || $link->account_id === $connection->account_id),
            409, 'Die Verbindung der Arbeit wurde geändert. Bitte den Kopierlauf erneut starten.');

        $current = collect($work->status['portal_submissions'] ?? [])->whereStrict('id', $selection['id']);
        $file = $current->first();
        $student = (int) ($file['student_id'] ?? 0);
        if ($current->count() !== 1 || ! in_array($student, array_column($group['students'], 'id'), true)
            || SchooltoolAssessmentJson::digest($file) !== $selection['receipt_digest']) {
            return ['status' => 'open', 'message' => 'Die Abgabe oder ihre Zuordnung wurde seit Beginn geändert.'];
        }
        try {
            $name = $this->candidateNames($work)[$student] ?? null;
            abort_unless($name && hash('sha256', $name) === $selection['candidate_digest'], 409,
                'Die Zuordnung zum persönlichen Prüfungsordner wurde seit Beginn geändert.');
            $path = TeachingWorkSubmissions::storagePath($work, $file);
            $size = $file['size'] ?? null;
            abort_unless($path && is_int($size) && $size > 0 && $size <= TeachingWorkSubmissions::MAX_FILE_KIB * 1024
                && Storage::disk('local')->size($path) === $size, 409, 'Das gespeicherte Portaloriginal fehlt oder ist unvollständig.');
            $bytes = Storage::disk('local')->get($path);
            abort_unless(is_string($bytes) && strlen($bytes) === $size && is_string($file['sha256'] ?? null)
                && hash_equals($file['sha256'], hash('sha256', $bytes)), 409, 'Die Prüfsumme des Portaloriginals stimmt nicht.');

            $candidates = $plan['candidates'];
            $person = $this->dropbox->findExerciseChildFolder($connection, $candidates, $name);
            $destination = $person ? $this->dropbox->findExerciseChildFolder($connection, $person, 'Abgaben') : null;
            abort_unless($destination, 409, 'Der persönliche Dropbox-Abgabeordner fehlt.');

            $status = $this->dropbox->copyPortalOriginal($connection, $destination, (string) ($file['name'] ?? ''), $bytes);
            $this->recordTarget($work, $plan, $selection, $destination['id']);

            return ['status' => $status];
        } catch (UnableToReadFile|UnableToRetrieveMetadata) {
            $this->recordTarget($work, $plan, $selection);

            return ['status' => 'open', 'message' => 'Das gespeicherte Portaloriginal konnte nicht gelesen werden.'];
        } catch (DropboxExerciseRequestFailed|HttpExceptionInterface $failure) {
            $this->recordTarget($work, $plan, $selection);

            return ['status' => 'open', 'message' => $failure instanceof DropboxExerciseRequestFailed
                ? $failure->safePublicMessage : $failure->getMessage()];
        }
    }

    /** Confirm a selected exam folder without reading or copying any portal original. */
    public function verifySelectedTarget(DropboxConnection $connection, TeachingCourseWork $work, string $folderId, string $date): array
    {
        $this->closedSelection($work, $date);
        $link = new TeachingWorkDropboxFolder(['dropbox_connection_id' => $connection->id,
            'teaching_course_work_id' => $work->id, 'folder_id' => $folderId]);
        $folder = $this->modules->portalSubmissionTarget($connection, $work, $link, $date);
        $candidates = $this->dropbox->findExerciseChildFolder($connection, $folder, 'Kandidaten');
        abort_unless($candidates, 409, 'Im ausgewählten Prüfungsordner fehlt der Kandidatenordner.');

        return ['folder' => ['id' => $folder['id'], 'name' => $folder['name'], 'path' => $folder['path_display'] ?? $folder['path_lower']],
            'candidates_id' => $candidates['id']];
    }

    public function rememberSelectedTarget(TeachingCourseWork $work, TeachingWorkDropboxFolder $link, string $date, array $target): void
    {
        $this->recordTarget($work, ['date' => $date, 'binding' => $this->binding($work, $link),
            'folder_id' => $target['folder']['id'], 'candidates' => ['id' => $target['candidates_id']], 'observed_at' => now()->toIso8601String()]);
    }

    private function recordTarget(TeachingCourseWork $work, array $plan, ?array $file = null, ?string $destinationId = null): void
    {
        $status = $work->status;
        $date = $plan['date'];
        $target = ['binding' => $plan['binding'], 'folder_id' => $plan['folder_id'], 'candidates_id' => $plan['candidates']['id']];
        $previous = $status['portal_submission_copy'][$date] ?? [];
        $sameTarget = array_intersect_assoc($target, $previous) === $target;
        $record = $target + ['observed_at' => $sameTarget && strtotime($previous['observed_at']) > strtotime($plan['observed_at'])
            ? $previous['observed_at'] : $plan['observed_at'],
            'files' => $sameTarget ? $previous['files'] : []];
        if ($file !== null) {
            unset($record['files'][$file['id']]);
        }
        if ($file !== null && $destinationId !== null) {
            // Replacing a personal Abgaben folder invalidates earlier confirmations for that person.
            $record['files'] = array_filter($record['files'], fn (array $proof): bool => $proof['candidate_digest'] !== $file['candidate_digest']
                || $proof['destination_id'] === $destinationId);
            $record['files'][$file['id']] = ['receipt_digest' => $file['receipt_digest'], 'candidate_digest' => $file['candidate_digest'],
                'destination_id' => $destinationId];
        }
        if ($previous !== $record && SchooltoolAssessmentJson::digest($previous) !== SchooltoolAssessmentJson::digest($record)) {
            $status['portal_submission_copy'][$date] = $record;
            $work->update(['status' => $status]);
        }
    }

    /** @return array<string, array{folder_id:?string, complete:bool, observed_at:?string, candidates_id?:?string, linked:bool}> */
    public function progress(TeachingCourseWork $work, array $groups, ?array $run = null): array
    {
        if ((! $work->studentReleaseEnded() && ! TeachingWorkSubmissions::expired($work))
            || (TeachingWorkSubmissions::method($work)['channel'] ?? null) !== 'portal'
            || empty($work->status['portal_submissions'])) {
            return [];
        }
        $records = $work->status['portal_submission_copy'] ?? [];
        if (app(TeachingAssessmentRuns::class)->pendingExerciseDeletion($work)) {
            return [];
        }
        $link = $this->targetBinding($work);
        if (! $link) {
            return [];
        }
        $progress = [];
        $binding = $this->binding($work, $link);
        $candidateNames = null;
        $cached = $link instanceof TeachingWorkModuleLink ? $this->modules->cachedPortalCopyTargets($work, $link) : null;
        $checkedAt = strtotime($cached['observed_at'] ?? '');
        $checkedDates = array_column($cached['matches'] ?? [], 'date');
        $dateWasChecked = fn (string $date): bool => ($cached['all_missing'] ?? false) || in_array($date, $checkedDates, true);
        $group = TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse)['folder_group'];
        $target = $run['portal_copy_target'] ?? null;
        $runDate = $run['date'] ?? null;
        if ($link instanceof TeachingWorkModuleLink && $target && $runDate
            && (! $dateWasChecked($runDate) || strtotime($target['observed_at'] ?? '') >= $checkedAt)
            && $this->targetPathMatches($work, $link, $group, $runDate, $target)) {
            $progress[$runDate] = ['folder_id' => $target['id'], 'complete' => false, 'observed_at' => $target['observed_at']];
        }
        foreach ($cached['matches'] ?? [] as $match) {
            $date = $match['date'];
            $folder = $match['folder'];
            if (($match['preparation']['status'] ?? null) === 'ready' && $this->targetPathMatches($work, $link, $group, $date, $folder)
                && (! isset($progress[$date]) || $checkedAt > strtotime($progress[$date]['observed_at'] ?? ''))) {
                $progress[$date] = ['folder_id' => $folder['id'], 'complete' => false, 'observed_at' => $cached['observed_at']];
                if (array_key_exists('portal_candidates_id', $match['preparation'])) {
                    $progress[$date]['candidates_id'] = $match['preparation']['portal_candidates_id'];
                }
            }
        }
        foreach ($records as $date => $record) {
            $observedAt = strtotime($record['observed_at']);
            if (($record['binding'] ?? null) !== $binding
                || (isset($progress[$date]) && strtotime($progress[$date]['observed_at'] ?? '') > $observedAt
                    && ($progress[$date]['folder_id'] !== $record['folder_id']
                        || (array_key_exists('candidates_id', $progress[$date]) && $progress[$date]['candidates_id'] !== $record['candidates_id'])))
                || (! isset($progress[$date]) && $dateWasChecked($date) && $checkedAt > $observedAt)) {
                continue;
            }
            $dateProgress = ['folder_id' => $record['folder_id'], 'candidates_id' => $record['candidates_id'],
                'observed_at' => $record['observed_at'], 'complete' => false];
            try {
                $selection = $this->reopening->options($work, $date);
                $eligible = array_column($selection['students'], 'id');
                $files = $this->activeFiles($work, $groups, $date, $eligible);
                $complete = $files->isNotEmpty() && $files->pluck('id')->duplicates()->isEmpty()
                    && $files->every(function (array $file) use ($work, $record, $eligible, &$candidateNames): bool {
                        $proof = $record['files'][$file['id'] ?? ''] ?? null;
                        $student = (int) ($file['student_id'] ?? 0);
                        if (! $proof || ! in_array($student, $eligible, true)
                            || $proof['receipt_digest'] !== SchooltoolAssessmentJson::digest($file)) {
                            return false;
                        }
                        $candidateNames ??= $this->candidateNames($work);
                        $name = $candidateNames[$student] ?? null;

                        return $name !== null && hash('sha256', $name) === $proof['candidate_digest'];
                    });
                $dateProgress['complete'] = $complete;
            } catch (ValidationException|HttpExceptionInterface) {
                // A deleted date or an ambiguous roster can no longer confirm a finished copy.
            }
            $progress[$date] = $dateProgress;
        }

        $commonDate = $work->date_for_all_groups?->format('Y-m-d');
        $dates = $groups === [] ? [$commonDate] : array_map(fn (array $group): ?string => $group['date'] ?? $commonDate, $groups);
        foreach (array_unique(array_filter($dates)) as $date) {
            // A saved link allows the copy to be offered even in a cold cache. Only an online
            // confirmation can supply its destination or count copies as finished.
            $progress[$date] ??= ['folder_id' => null, 'complete' => false, 'observed_at' => null];
            $progress[$date]['linked'] = true;
        }

        return array_intersect_key($progress, array_flip(array_filter($dates)));
    }

    private function targetPathMatches(TeachingCourseWork $work, TeachingWorkModuleLink $link, ?string $group, string $date, array $folder): bool
    {
        $expected = $link->folder_path.'/Leistungsfeststellungen/'.$date.' – '.$group.' – '.trim($work->title);

        return $group !== null && strcasecmp($folder['path'], $expected) === 0;
    }

    private function targetBinding(TeachingCourseWork $work): TeachingWorkModuleLink|TeachingWorkDropboxFolder|null
    {
        $link = $this->modules->current($work);
        if ($link || ! $this->dropbox->installed()) {
            return $link;
        }

        $folders = TeachingWorkDropboxFolder::query()->where('teaching_course_work_id', $work->id)->limit(2)->get();

        return $folders->count() === 1 ? $folders->first() : null;
    }

    private function binding(TeachingCourseWork $work, TeachingWorkModuleLink|TeachingWorkDropboxFolder $link): string
    {
        return SchooltoolAssessmentJson::digest([$link->getAttributes(), $work->title,
            TeachingAssessmentTargetGuard::courseGroup($work->teachingCourse)['folder_group']]);
    }

    private function activeFiles(TeachingCourseWork $work, array $groups, string $date, array $eligible): Collection
    {
        $assigned = $groups === [] ? array_map('intval', $work->status['student_distribution']['student_ids'] ?? [])
            : TeachingWorkSubmissions::groupAccounts($work, $date, $groups);
        $inScope = array_unique([...$eligible, ...$assigned]);

        return collect($work->status['portal_submissions'] ?? [])
            ->filter(fn ($file): bool => is_array($file) && empty($file['withdrawn_at'])
                && in_array((int) ($file['student_id'] ?? 0), $inScope, true))
            ->values();
    }

    private function closedSelection(TeachingCourseWork $work, ?string $date, bool $lock = false): array
    {
        abort_unless((TeachingWorkSubmissions::method($work)['channel'] ?? null) === 'portal' && $work->studentReleaseEnded(),
            409, 'Portalabgaben können erst nach Ende der Freigabe kopiert werden.');
        abort_if(app(TeachingAssessmentRuns::class)->pendingExerciseDeletion($work), 409, 'Die Löschung des Prüfungsordners läuft noch.');
        $selection = $this->reopening->options($work, $date, $lock);
        abort_unless($selection['date'], 409, 'Für die Kopie wird ein eindeutiger Durchführungstermin benötigt.');
        abort_if(collect($selection['students'])->contains(fn (array $student): bool => $student['open_until'] !== null),
            409, 'Für diese Termingruppe ist die Portalabgabe noch einmal geöffnet.');

        return $selection;
    }

    /** @return array<int, string> */
    private function candidateNames(TeachingCourseWork $work): array
    {
        $people = collect($this->export->participants($work->teachingCourse));
        $names = $people->map(fn (array $person): string => $this->assessment->personName($person));
        // A former course member or a member without a user account can also own an existing folder.
        $counts = $names->map(fn (string $name): string => mb_strtolower($name))->countBy();

        return $people->mapWithKeys(function (array $person, int $index) use ($names, $counts): array {
            $name = $names[$index];

            return $person['active'] && $person['schooltool_person_id'] && $counts->get(mb_strtolower($name)) === 1
                ? [(int) $person['schooltool_person_id'] => $name] : [];
        })->all();
    }

    private function key(TeachingCourseWork $work, User $actor, string $run): string
    {
        return 'portal-original-copy:'.$work->id.':'.$actor->id.':'.$run;
    }
}
