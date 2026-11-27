<?php

namespace App\Services;

use App\Exceptions\DropboxExerciseRequestFailed;
use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use App\Support\SchooltoolAssessmentJson;
use App\Support\TeachingAssessmentProgress;
use Illuminate\Cache\ArrayLock;
use Illuminate\Cache\DatabaseLock;
use Illuminate\Cache\FileLock;
use Illuminate\Cache\Lock;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class TeachingDirectAssessment
{
    private const LOCK_LEASE_SECONDS = 300;

    private ?Lock $activeModuleLock = null;

    public function __construct(
        private DropboxWorkImport $dropbox,
        private DropboxModuleLinks $modules,
        private TeachingWorkAiExport $export,
        private TeachingAssessmentTemplate $templates,
        private TeachingDirectAssessmentMc $mc,
        private TeachingAssessmentTargetGuard $targets,
    ) {}

    /** @return array<string, string> */
    public function exerciseDeletionCandidate(TeachingCourseWork $work, User $actor, ?string $date, string $folderId): array
    {
        [$link, $connection] = $this->exerciseDeletionBinding($work, $actor);
        $exercise = $this->modules->exerciseFolder($connection, $work, $link, $date);

        return $this->confirmedExerciseDeletionFolder($exercise, $folderId);
    }

    private function exerciseDeletionBinding(TeachingCourseWork $work, User $actor): array
    {
        abort_unless($work->automationEnabled(), 422, 'Die automatische Beurteilung ist ausgeschaltet.');
        $this->assertUnreleased($work);
        abort_if(isset($work->status['deletion_receipt']) || TeachingWorkDropboxFolder::query()
            ->where('teaching_course_work_id', $work->id)->exists(), 409, 'Die Arbeit hat bereits eine separate Ordnerverknüpfung oder wird gelöscht.');
        $link = $this->modules->current($work);
        $connection = $this->dropbox->connection($actor);
        abort_unless($link && $link->dropbox_connection_id === $connection->id
            && $link->account_id === $connection->account_id, 409, 'Die bestätigte Modulverbindung ist nicht verfügbar.');

        return [$link, $connection];
    }

    /** @return array<string, string> */
    private function confirmedExerciseDeletionFolder(array $exercise, string $folderId): array
    {
        $folder = ($exercise['reusable'] ?? null) === 'linked' ? ($exercise['reusable_folder'] ?? null)
            : (($exercise['preparation']['status'] ?? null) === 'ready' ? $exercise['folder'] : null);
        abort_unless(($folder['id'] ?? null) === $folderId, 409, 'Der angezeigte Prüfungsordner konnte für diesen Termin nicht bestätigt werden.');

        return $folder;
    }

    public function deleteExercise(TeachingCourseWork $work, User $actor, ?string $date, string $folderId, ?\Closure $progress = null): void
    {
        [$link, $connection] = $this->exerciseDeletionBinding($work, $actor);
        [$guard, $lock] = $this->acquireModuleLock($link->folder_id);
        try {
            $exercise = $this->modules->exerciseFolder($connection, $work, $link, $date);
            $confirmed = $this->confirmedExerciseDeletionFolder($exercise, $folderId);
            $alreadyReset = ($exercise['reusable'] ?? null) === 'linked';
            $targets = $this->modules->deletionTargets($work, $actor);
            $folder = collect($targets['folders'] ?? [])->firstWhere('id', $folderId);
            abort_unless($folder && $folder['path'] === $confirmed['path'], 409,
                'Die Zuordnung des Prüfungsordners hat sich geändert.');
            $document = $this->dropbox->readExerciseDocument($connection, $folderId, 'status.json');
            $status = $document ? json_decode($document['contents'], true) : null;
            abort_unless(is_array($status) && ($status['schooltool']['work_id'] ?? null) === $work->id
                && ($status['schooltool']['course_id'] ?? null) === $work->teaching_course_id
                && is_string($status['scheduled_date'] ?? null) && is_string($status['folder_group'] ?? null)
                && ($date === null || $date === $status['scheduled_date']), 409, 'Die Statusdatei gehört nicht zum angezeigten Termin.');
            $initialStatus = $this->json($this->initialStatus($work, ['course' => ['folder_group' => $status['folder_group']],
                'work' => ['scheduled_date' => $status['scheduled_date']]]));
            abort_unless($alreadyReset ? $document['contents'] === $initialStatus : ($status['prepared'] ?? null) === true,
                409, 'Die Leistungsfeststellung wurde inzwischen geändert.');
            try {
                $statusRevision = $document['rev'];
                if (! $alreadyReset) {
                    $progress?->__invoke('exercise_delete_contents');
                    $this->refreshModuleLock();
                    $this->dropbox->clearExamFolder($connection, $folderId, $folder['path'], $statusRevision,
                        $progress ? fn (int $completed, int $total) => $progress('exercise_delete_contents', [
                            'detail' => "$completed von $total Einträgen gelöscht",
                        ]) : null);
                    $progress?->__invoke('exercise_delete_reset');
                    $this->refreshModuleLock();
                    $statusRevision = $this->dropbox->writeExerciseDocument($connection, $folderId, 'status.json', $initialStatus, $statusRevision);
                }
                $progress?->__invoke('exercise_delete_verify', $alreadyReset ? ['skipped' => ['exercise_delete_contents', 'exercise_delete_reset']] : []);
                $this->verifyFile($connection, $folderId, 'status.json', $initialStatus, $statusRevision);
                $this->assertInitialStatusOnly($connection, $folderId);
                abort_unless(($this->dropbox->findFolderById($connection, $folderId)['path_lower'] ?? null) === mb_strtolower($folder['path']),
                    409, 'Der erhaltene Prüfungsordner konnte nicht bestätigt werden.');
                abort_unless($this->modules->current($work)?->getAttributes() === $link->getAttributes(),
                    409, 'Die erhaltene Modulverbindung konnte nicht bestätigt werden.');
            } finally {
                $this->modules->forgetExerciseCache($work, $actor);
            }
        } finally {
            $this->releaseModuleLock($guard, $lock);
        }
    }

    /** @return array<string, mixed> */
    public function preview(TeachingCourseWork $work, User $actor, ?string $date, float $expectedPoints, ?int $expectedMcId = null, bool $summary = false, ?\Closure $progress = null): array
    {
        $link = $this->modules->current($work);
        abort_unless($link, 422, 'Zuerst das Lernmodul mit dieser Arbeit verknüpfen.');
        $connection = $this->dropbox->connection($actor);
        abort_unless($connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id
            && $this->dropbox->canWrite($connection), 422, 'Die bestätigte Dropbox-Verbindung oder Schreibfreigabe fehlt.');
        $progress?->__invoke('start', 'assessment_candidates');
        $context = $this->context($work, $actor, $date);
        $this->validateContext($context);
        $progress?->__invoke('completed', 'assessment_candidates');
        $this->targets->assertUnique($work, $link, $context['work']['scheduled_date'], $context['course']['folder_group']);
        $this->assertUnreleased($work);
        $mc = $this->mc->snapshot($work, $context['work']['scheduled_date'], $expectedMcId);
        $progress?->__invoke('start', 'assessment_assignment');
        $templateEvents = [];
        $templateReport = function (string $event, string $step) use ($progress, &$templateEvents): void {
            $templateEvents[] = [$event, $step];
            $progress?->__invoke($event, $step);
        };
        $template = $this->templates->inspect($actor, $link, $templateReport);
        if ($templateEvents === []) {
            $progress?->__invoke('completed', 'assessment_assignment');
            $progress?->__invoke('start', 'assessment_criteria');
            $progress?->__invoke('completed', 'assessment_criteria');
        }
        $progress?->__invoke('start', 'assessment_target');
        abort_unless(abs($template['points'] - $expectedPoints) < 0.000001, 409, 'Die Vorlagenpunkte wurden geändert. Bitte erneut prüfen.');
        $status = $this->modules->exercise($connection, $work, $link, $context['work']['scheduled_date'], refresh: true);
        abort_unless(($status['status'] ?? null) === 'missing', 409, $status['message'] ?? 'Für diese Durchführung ist bereits ein Prüfungsordner vorhanden.');
        $module = $this->dropbox->metadata($connection, $link->folder_id);
        abort_unless(($module['id'] ?? null) === $link->folder_id && strcasecmp($module['path_lower'], $link->folder_path) === 0
            && ! $this->dropbox->isTemporaryFolderPath($module['path_lower']),
            409, 'Der Zielordner wurde geändert.');
        $reusable = null;
        $linkedStatus = $this->json($this->initialStatus($work, $context));
        $this->checkExisting($connection, $module, $work->id, $work->teaching_course_id, $context, null, $reusable, $linkedStatus,
            $this->json($this->status($work, $context, $template['points'], false)), $template);
        $id = (string) Str::uuid();
        $plan = ['context_digest' => $this->digest($context), 'link' => $link->getAttributes(), 'module' => $module,
            'template' => $this->templateIdentity($template), 'date' => $date, 'mc_id' => $mc['id'] ?? null, 'reusable' => $reusable,
            'mc_digest' => SchooltoolAssessmentJson::digest(['mc' => $mc])];
        Cache::store('teaching-assessment')->put($this->key($work, $actor, $id), $plan, now()->addMinutes(30));
        $progress?->__invoke('completed', 'assessment_target');

        $result = ['id' => $id, 'points' => $template['points'], 'target' => ($module['path_display'] ?? $module['path_lower']).'/Leistungsfeststellungen/'.$this->folderName($context),
            'scheduled_date' => $context['work']['scheduled_date'], 'deadline' => $context['work']['main_deadline'],
            'multiple_choice' => $mc ? array_intersect_key($mc, array_flip(['id', 'title', 'points', 'status'])) : null,
            'combined_points' => $template['points'] + ($mc['points'] ?? 0), 'reusable_folder' => $reusable['kind'] ?? null];
        if ($summary) {
            return $result + ['participants' => array_map(fn (array $person): array => ['name' => $this->personName($person),
                'sex' => is_string($person['sex'] ?? null) && trim($person['sex']) !== '' ? trim($person['sex']) : null], $context['course_roster']['participants']),
                'checks' => ['assignment' => 'ok', 'criteria' => 'ok']];
        }

        return $result + ['participants' => array_map(fn (array $person): array => ['name' => $this->personName($person)], $context['course_roster']['participants']),
            'criteria_markdown' => $template['documents']['criteria_markdown']['bytes'],
            'documents' => array_map(fn (array $document): array => ['name' => $document['name'], 'base64' => base64_encode($document['bytes'])],
                array_intersect_key($template['documents'], array_flip(['assignment', 'criteria'])))];
    }

    /** @return array<string, mixed> */
    public function initializeLinkedFolder(TeachingCourseWork $work, User $actor): array
    {
        $work = $work->fresh('teachingCourse');
        $dates = $this->targets->dates($work);
        abort_unless(count($dates) === 1, 422, 'Die Verknüpfung ist gespeichert. Der Durchführungstermin ist nicht eindeutig; bitte zuerst den Termin der Arbeit festlegen.');
        $link = $this->modules->current($work);
        abort_unless($link, 409, 'Die Modulverbindung ist nicht bestätigt.');
        $connection = $this->dropbox->connection($actor);
        abort_unless($connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id
            && $this->dropbox->canWrite($connection), 422, 'Die Verknüpfung ist gespeichert. Für den Prüfungsordner fehlt die Dropbox-Schreibfreigabe.');
        $module = $this->dropbox->metadata($connection, $link->folder_id);
        abort_unless(($module['id'] ?? null) === $link->folder_id && strcasecmp($module['path_lower'], $link->folder_path) === 0
            && ! $this->dropbox->isTemporaryFolderPath($module['path_lower']), 409, 'Das verbundene Lernmodul wurde geändert.');
        [$guard, $lock] = $this->acquireModuleLock($module['id']);
        try {
            $status = $this->modules->exercise($connection, $work, $link, $dates[0], refresh: true);
            if (($status['status'] ?? null) === 'found' && ($status['preparation']['status'] ?? null) === 'ready') {
                return ['status' => 'already_prepared', 'folder' => $status['folder']];
            }
            abort_unless(($status['status'] ?? null) === 'missing', 409, $status['message'] ?? 'Der Prüfungsordner ist nicht eindeutig.');
            $context = $this->context($work, $actor, $dates[0]);
            $this->validateContext($context);
            $this->targets->assertUnique($work, $link, $dates[0], $context['course']['folder_group']);
            $this->assertUnreleased($work);
            abort_unless($this->modules->current($work)?->getAttributes() === $link->getAttributes()
                && $this->dropbox->metadata($connection, $link->folder_id) === $module && empty($module['sharing_info']),
                409, 'Das Lernmodul ist geteilt oder wurde während der Prüfung geändert.');
            $linkedStatus = $this->json($this->initialStatus($work, $context));
            $reusable = null;
            $root = $this->checkExisting($connection, $module, $work->id, $work->teaching_course_id, $context, null, $reusable, $linkedStatus);
            if (($reusable['kind'] ?? null) === 'linked') {
                $this->modules->rememberInitializedExercise($connection, $work, $link, $dates[0], $reusable['folder']);

                return ['status' => 'initialized', 'folder' => $reusable['folder']];
            }
            abort_unless($this->digest($this->context($work, $actor, $dates[0])) === $this->digest($context)
                && $this->modules->current($work)?->getAttributes() === $link->getAttributes(),
                409, 'Arbeit oder Modulverbindung wurde während der Prüfung geändert.');
            $this->targets->assertUnique($work, $link, $dates[0], $context['course']['folder_group']);
            $root ??= $this->createLockedFolder($connection, $module['id'], 'Leistungsfeststellungen', $module['path_lower']);
            abort_unless(empty($root['sharing_info']), 409, 'Der Prüfungsüberordner ist geteilt und muss geprüft werden.');
            $folder = $reusable['folder'] ?? $this->createLockedFolder($connection, $root['id'], $this->folderName($context), $root['path_lower']);
            abort_unless(empty($folder['sharing_info']), 409, 'Der Prüfungsordner ist geteilt und muss geprüft werden.');
            $this->write($connection, $folder['id'], 'status.json', $linkedStatus);
            $this->assertInitialStatusOnly($connection, $folder['id']);
            $this->modules->rememberInitializedExercise($connection, $work, $link, $dates[0], $folder);

            return ['status' => 'initialized', 'folder' => $folder];
        } finally {
            $this->releaseModuleLock($guard, $lock);
        }
    }

    /** @return array<string, mixed> */
    public function initializeNewLinkedFolder(TeachingCourseWork $work, User $actor, ?array $module): array
    {
        $work = $work->fresh('teachingCourse');
        $dates = $this->targets->dates($work);
        abort_unless(count($dates) === 1, 422, 'Die Verknüpfung ist gespeichert. Der Durchführungstermin ist nicht eindeutig; bitte zuerst den Termin der Arbeit festlegen.');
        $link = $this->modules->current($work);
        $connection = $this->dropbox->connection($actor);
        abort_unless($link && $connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id
            && $this->dropbox->canWrite($connection) && $work->automationEnabled(),
            409, 'Modulverknüpfung oder Schreibfreigabe ist nicht bestätigt.');
        $retry = $module === null;
        $module ??= $this->dropbox->metadata($connection, $link->folder_id);
        abort_unless($module['id'] === $link->folder_id && $module['path_lower'] === mb_strtolower($link->folder_path)
            && empty($module['sharing_info']) && ! $this->dropbox->isTemporaryFolderPath($module['path_lower']),
            409, 'Das Lernmodul ist geteilt oder wurde während der Prüfung geändert.');

        $context = $this->context($work, $actor, $dates[0]);
        $this->validateContext($context);
        $this->targets->assertUnique($work, $link, $dates[0], $context['course']['folder_group']);
        $this->assertUnreleased($work);

        $root = $this->dropbox->findExerciseChildFolder($connection, $module, 'Leistungsfeststellungen')
            ?? $this->dropbox->createExerciseChildFolder($connection, $module['id'], 'Leistungsfeststellungen', $module['path_lower'], verifyCreated: false);
        $name = $this->folderName($context);
        $status = $this->json($this->initialStatus($work, $context));
        $folder = $retry ? $this->dropbox->findExerciseChildFolder($connection, $root, $name) : null;
        $writeStatus = $folder === null || ! $this->hasInitialStatus($connection, $folder, $status);
        $folder ??= $this->dropbox->createExerciseChildFolder($connection, $root['id'], $name, $root['path_lower'], verifyCreated: false);
        if ($writeStatus) {
            $this->dropbox->writeExerciseDocument($connection, $folder['id'], 'status.json', $status,
                revision: null, expectedFolderPath: $folder['path_lower']);
        }
        $this->modules->rememberInitializedExercise($connection, $work, $link, $dates[0], $folder, lock: false);

        return ['status' => 'initialized', 'folder' => $folder];
    }

    private function hasInitialStatus(DropboxConnection $connection, array $folder, string $status): bool
    {
        $children = $this->children($connection, $folder['id'], includeSharing: true);
        if ($children === []) {
            return false;
        }
        $file = $children[0];
        abort_unless(count($children) === 1 && ($file['.tag'] ?? null) === 'file' && $file['name'] === 'status.json'
            && ($file['path_lower'] ?? null) === $folder['path_lower'].'/status.json' && is_string($file['rev'] ?? null)
            && empty($file['sharing_info']) && ($file['has_explicit_shared_members'] ?? false) !== true,
            409, 'Der vorhandene Prüfungsordner enthält andere oder geteilte Daten. Bitte die Zuordnung prüfen.');
        $this->verifyFile($connection, $folder['id'], 'status.json', $status, $file['rev']);

        return true;
    }

    /** @return array<string, mixed> */
    public function store(TeachingCourseWork $work, User $actor, string $id, ?\Closure $progress = null): array
    {
        $key = $this->key($work, $actor, $id);
        $plans = Cache::store('teaching-assessment');
        $plan = $plans->get($key);
        abort_unless(is_array($plan), 409, 'Die Vorschau ist abgelaufen. Bitte erneut prüfen.');
        $progress?->__invoke('configure_mc', null, ($plan['mc_id'] ?? null) !== null);
        if (isset($plan['completed'])) {
            $progress?->__invoke('replayed');

            return $plan['completed'];
        }
        [$guard, $lock] = $this->acquireModuleLock($plan['module']['id']);
        try {
            $plan = $plans->get($key);
            abort_unless(is_array($plan), 409, 'Die Vorschau ist abgelaufen. Bitte erneut prüfen.');
            if (isset($plan['completed'])) {
                $progress?->__invoke('replayed');

                return $plan['completed'];
            }
            $link = $this->modules->current($work);
            abort_unless($link && $link->getAttributes() === $plan['link'], 409, 'Die Modulverbindung wurde geändert. Bitte die Vorschau erneuern.');
            $connection = $this->dropbox->connection($actor);
            abort_unless($connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id
                && $this->dropbox->canWrite($connection), 409, 'Die Dropbox-Schreibfreigabe wurde geändert.');
            $context = $this->context($work, $actor, $plan['date']);
            $this->targets->assertUnique($work, $link, $context['work']['scheduled_date'], $context['course']['folder_group']);
            $this->assertUnreleased($work);
            abort_unless($this->digest($context) === $plan['context_digest'], 409, 'Arbeitsdaten oder Prüfungskandidaten wurden geändert. Bitte die Vorschau erneuern.');
            $mc = null;
            if ($plan['mc_id'] === null) {
                $mc = $this->mc->snapshot($work, $context['work']['scheduled_date'], null);
                abort_unless(SchooltoolAssessmentJson::digest(['mc' => $mc]) === $plan['mc_digest'],
                    409, 'Der MC-Test wurde geändert. Bitte die Vorschau erneuern.');
            }
            $template = $this->templates->inspect($actor, $link);
            abort_unless($this->templateIdentity($template) === $plan['template'], 409,
                'Die Prüfungsvorlage wurde geändert. Bitte die Vorschau erneuern.');
            $module = $this->dropbox->metadata($connection, $link->folder_id);
            abort_unless($module === $plan['module'] && ! $this->dropbox->isTemporaryFolderPath($module['path_lower']), 409, 'Der Zielordner wurde geändert.');
            $started = $plan['started'] ?? null;
            if (! $started) {
                $currentStatus = $this->modules->exercise($connection, $work, $link, $context['work']['scheduled_date'], refresh: true);
                abort_unless(($currentStatus['status'] ?? null) === 'missing', 409,
                    $currentStatus['message'] ?? 'Der Anlagezustand hat sich geändert. Bitte erneut prüfen.');
            }
            $reusable = null;
            $linkedStatus = $this->json($this->initialStatus($work, $context));
            $root = $this->checkExisting($connection, $module, $work->id, $work->teaching_course_id, $context, $started['folder']['id'] ?? null, $reusable, $linkedStatus,
                $this->json($this->status($work, $context, $template['points'], false)), $template);
            $progress?->__invoke('completed', 'assessment_check');
            if ($plan['mc_id'] !== null) {
                $progress?->__invoke('start', 'assessment_mc');
                $mc = $this->mc->snapshot($work, $context['work']['scheduled_date'], $plan['mc_id']);
                abort_unless(SchooltoolAssessmentJson::digest(['mc' => $mc]) === $plan['mc_digest'],
                    409, 'Der MC-Test wurde geändert. Bitte die Vorschau erneuern.');
                $progress?->__invoke('completed', 'assessment_mc');
            }
            $candidateLabels = [];
            foreach ($context['course_roster']['participants'] as $index => $person) {
                $candidateLabels['assessment_candidate_'.$index] = 'Kandidatenordner: '.$this->personName($person);
            }
            $progress?->__invoke('candidates', null, $candidateLabels);
            $progress?->__invoke('start', 'assessment_folder');
            if ($started) {
                abort_unless($root && $root['id'] === $started['root']['id'], 409, 'Der begonnene Prüfungsordner ist nicht mehr eindeutig.');
                $folder = $this->dropbox->metadata($connection, $started['folder']['id']);
                abort_unless($folder === $started['folder'] && dirname($folder['path_lower']) === $root['path_lower'], 409,
                    'Der begonnene Prüfungsordner wurde geändert.');
            } else {
                abort_unless($reusable === ($plan['reusable'] ?? null), 409, 'Der vorgesehene Prüfungsordner wurde verändert. Bitte die Vorschau erneuern.');
                if (! $root) {
                    $root = $this->createLockedFolder($connection, $module['id'], 'Leistungsfeststellungen', $module['path_lower']);
                }
                $folder = $reusable ? $reusable['folder'] : $this->createLockedFolder($connection, $root['id'], $this->folderName($context), $root['path_lower']);
                $plan['started'] = ['folder' => $folder, 'root' => $root];
                $plans->put($key, $plan, now()->addMinutes(30));
            }
            $path = $folder['path_display'] ?? $folder['path_lower'];
            $phase = 'status';
            $activeCandidate = null;
            $statusConfirmed = false;
            $savingJournal = false;
            $journal = null;
            $saveJournal = null;
            try {
                $incomplete = $this->json($this->status($work, $context, $template['points'], false));
                $complete = $this->json($this->status($work, $context, $template['points'], true));
                $existingStatus = null;
                $statusRevision = $reusable['revision'] ?? null;
                $alreadyPrepared = false;
                if (($reusable['kind'] ?? null) === 'partial' || ($started && ! $reusable)) {
                    $existingStatus = $this->dropbox->readExerciseDocument($connection, $folder['id'], 'status.json');
                    abort_unless($existingStatus && ($this->matchesStatus($existingStatus['contents'], $incomplete)
                        || ($started && ! $reusable && $this->matchesStatus($existingStatus['contents'], $complete)))
                        && ($statusRevision === null || $existingStatus['rev'] === $statusRevision), 409, 'Der gespeicherte Status ist unklar oder wurde geändert.');
                    $statusRevision = $existingStatus['rev'];
                    $alreadyPrepared = $this->matchesStatus($existingStatus['contents'], $complete);
                }
                if (! $alreadyPrepared) {
                    $previous = $existingStatus ? json_decode($existingStatus['contents'], true, 32, JSON_THROW_ON_ERROR)['progress'] ?? null : null;
                    $journal = TeachingAssessmentProgress::begin($previous, $plan['mc_id'] !== null);
                    $saveJournal = function (bool $prepared = false) use ($connection, $folder, $journal, $incomplete, $complete, &$statusRevision, &$savingJournal): void {
                        $savingJournal = true;
                        $record = json_decode($prepared ? $complete : $incomplete, true, 32, JSON_THROW_ON_ERROR);
                        $statusRevision = $this->write($connection, $folder['id'], 'status.json', $this->json($record + ['progress' => $journal->record()]), $statusRevision);
                        $savingJournal = false;
                    };
                    $saveJournal();
                }
                if ($reusable && $reusable['kind'] !== 'partial') {
                    $this->assertInitialStatusOnly($connection, $folder['id']);
                }
                $statusConfirmed = true;
                $phase = 'documents';
                $progress?->__invoke('completed', 'assessment_folder');
                $progress?->__invoke('start', 'assessment_documents', 'Kandidatenbereich anlegen oder bestätigen');
                $phase = 'candidate_root';
                $candidates = $this->ensureFolder($connection, $folder, 'Kandidaten', $alreadyPrepared);
                $phase = 'criteria_markdown';
                $progress?->__invoke('detail', 'assessment_documents', 'Beurteilungskriterien als Markdown und PDF bestätigen');
                $this->ensureFile($connection, $folder['id'], 'Beurteilung.md', $template['documents']['criteria_markdown']['bytes'], $alreadyPrepared);
                $phase = 'criteria_pdf';
                $this->ensureFile($connection, $folder['id'], 'Beurteilung.pdf', $template['documents']['criteria']['bytes'], $alreadyPrepared);
                $assignment = $template['documents']['assignment'];
                if (! $alreadyPrepared) {
                    $phase = 'assignment_file';
                    $progress?->__invoke('detail', 'assessment_documents', 'Gemeinsames Aufgabenblatt speichern und prüfen');
                    $this->ensureFile($connection, $folder['id'], $assignment['name'], $assignment['bytes'], false);
                }
                $copies = [];
                $createdFolders = [[$candidates, $folder]];
                $candidateEntries = $this->children($connection, $candidates['id']);
                $journal?->confirmDocuments();
                $saveJournal?->__invoke();
                $progress?->__invoke('completed', 'assessment_documents');
                $lastCandidate = array_key_last($context['course_roster']['participants']);
                foreach ($context['course_roster']['participants'] as $index => $person) {
                    $activeCandidate = $index;
                    $phase = 'candidate_folder';
                    $candidateStep = 'assessment_candidate_'.$index;
                    $progress?->__invoke('start', $candidateStep, 'Persönlichen Prüfungsordner anlegen oder bestätigen');
                    $personFolder = $this->ensureFolder($connection, $candidates, $this->personName($person), $alreadyPrepared, $candidateEntries);
                    $createdFolders[] = [$personFolder, $candidates];
                    $sectionEntries = $this->children($connection, $personFolder['id']);
                    $progress?->__invoke('detail', $candidateStep, 'Angaben, Abgaben und Auswertung vorbereiten, Aufgabenblatt bestätigen');
                    foreach (['Angaben', 'Abgaben', 'Auswertung'] as $section) {
                        $phase = 'candidate_subfolder';
                        $child = $this->ensureFolder($connection, $personFolder, $section, $alreadyPrepared, $sectionEntries);
                        $createdFolders[] = [$child, $personFolder];
                        if ($section === 'Angaben') {
                            $phase = 'assignment_file';
                            $this->ensureFile($connection, $child['id'], $template['documents']['assignment']['name'], $template['documents']['assignment']['bytes'], $alreadyPrepared);
                            $copies[] = $child['id'];
                        }
                    }
                    $journal?->confirmCandidate($index, $index === $lastCandidate);
                    $saveJournal?->__invoke();
                    $progress?->__invoke('completed', $candidateStep);
                }
                $activeCandidate = null;
                $phase = 'verification';
                $progress?->__invoke('start', 'assessment_verify', 'Unterlagen, Ordnerzuordnung und Abschlussstatus rückprüfen');
                $this->verifyFile($connection, $folder['id'], 'Beurteilung.md', $template['documents']['criteria_markdown']['bytes']);
                $this->verifyFile($connection, $folder['id'], 'Beurteilung.pdf', $template['documents']['criteria']['bytes']);
                if (! $alreadyPrepared) {
                    $this->verifyFile($connection, $folder['id'], $assignment['name'], $assignment['bytes']);
                }
                foreach ($copies as $copy) {
                    $this->verifyFile($connection, $copy, $template['documents']['assignment']['name'], $template['documents']['assignment']['bytes']);
                }
                foreach ($createdFolders as [$created, $parent]) {
                    $confirmed = $this->dropbox->metadata($connection, $created['id']);
                    abort_unless(($confirmed['id'] ?? null) === $created['id'] && ($confirmed['name'] ?? null) === $created['name']
                        && dirname($confirmed['path_lower']) === $parent['path_lower'], 409, 'Ein Kandidatenordner konnte nicht bestätigt werden.');
                }
                $actualCandidates = array_values(array_filter($this->children($connection, $candidates['id']), fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'));
                abort_unless(count($actualCandidates) === count($copies), 409, 'Die Kandidatenordner sind nicht vollständig bestätigt.');
                $this->assertUnreleased($work);
                abort_unless($this->digest($this->context($work, $actor, $plan['date'])) === $this->digest($context), 409, 'Arbeitsdaten wurden während der Anlage geändert.');
                abort_unless($this->modules->current($work)?->getAttributes() === $plan['link']
                    && $this->templateIdentity($this->templates->inspect($actor, $link)) === $this->templateIdentity($template),
                    409, 'Modulverbindung oder Vorlagen wurden während der Anlage geändert.');
                abort_unless(SchooltoolAssessmentJson::digest(['mc' => $this->mc->snapshot($work, $context['work']['scheduled_date'], $plan['mc_id'])]) === $plan['mc_digest'],
                    409, 'Der MC-Test wurde während der Anlage geändert.');
                $checkedRoot = $this->checkExisting($connection, $module, $work->id, $work->teaching_course_id, $context, $folder['id']);
                abort_unless($checkedRoot && $checkedRoot['id'] === $root['id'], 409, 'Der Prüfungsbestand wurde während der Anlage geändert.');
                if (($reusable['kind'] ?? null) === 'partial') {
                    $this->validateResumeTree($connection, $folder, $context, $template, $this->children($connection, $folder['id'], includeSharing: true));
                }
                if ($journal) {
                    $journal->complete();
                    $saveJournal(true);
                    $journal = null;
                } else {
                    $this->verifyFile($connection, $folder['id'], 'status.json', $existingStatus['contents'], $statusRevision);
                }
                $this->modules->forgetExerciseCache($work, $actor);
                $confirmed = $this->modules->exercise($connection, $work, $link, $context['work']['scheduled_date'], refresh: true);
                abort_unless(($confirmed['status'] ?? null) === 'found' && ($confirmed['preparation']['status'] ?? null) === 'ready'
                    && ($confirmed['folder']['id'] ?? null) === $folder['id'], 409, 'Der vollständige Status konnte nicht online bestätigt werden.');
            } catch (Throwable $exception) {
                $error = $exception instanceof DropboxExerciseRequestFailed ? $exception->diagnostic()
                    : ['operation' => 'application', 'category' => 'unconfirmed', 'http_status' => null];
                logger()->warning('Direct assessment creation incomplete', ['phase' => $phase, 'candidate_index' => $activeCandidate,
                    'exception_class' => get_class($exception),
                    'endpoint' => $exception instanceof DropboxExerciseRequestFailed ? $exception->endpoint() : null] + $error);
                if ($statusConfirmed && $journal && ! $savingJournal) {
                    try {
                        $journal->fail($phase, $activeCandidate, $error);
                        $saveJournal();
                    } catch (Throwable $journalError) {
                        logger()->warning('Direct assessment failure checkpoint unconfirmed', ['phase' => $phase,
                            'exception_class' => get_class($journalError)]);
                    }
                }
                $confirmed = $statusConfirmed ? 'Prüfungsordner und Statusdatei sind bestätigt. ' : 'Der Prüfungsordner ist angelegt; die Statusdatei ist noch nicht bestätigt. ';
                $detail = $exception instanceof DropboxExerciseRequestFailed ? ' '.$exception->safePublicMessage : '';
                throw ValidationException::withMessages(['generation' => $confirmed.'Die Anlage unter '.$path.' ist unvollständig oder nicht vollständig bestätigt.'.$detail.' Sie können denselben Vorgang erneut versuchen; vorhandene Daten werden geprüft und nicht überschrieben.']);
            }
            $result = ['status' => 'created', 'folder' => ['id' => $folder['id'], 'name' => $folder['name'], 'path' => $path],
                'participants_count' => count($copies), 'points' => $template['points'], 'scheduled_date' => $context['work']['scheduled_date']];
            $result['multiple_choice'] = $mc ? array_intersect_key($mc, array_flip(['id', 'title', 'points', 'status'])) : null;
            $plan['completed'] = $result;
            $plans->put($key, $plan, now()->addMinutes(30));
            $progress?->__invoke('completed', 'assessment_verify');

            return $result;
        } finally {
            $this->releaseModuleLock($guard, $lock);
        }
    }

    /** @return array<string, mixed> */
    private function context(TeachingCourseWork $work, User $actor, ?string $date): array
    {
        return $this->export->buildExercise($work->fresh('teachingCourse'), $actor, false, $date)['context'];
    }

    /** @param array<string, mixed> $context */
    private function validateContext(array $context): void
    {
        abort_unless(is_string($context['work']['scheduled_date'] ?? null) && is_string($context['course']['folder_group'] ?? null)
            && filled($context['work']['title'] ?? null) && ($context['course_roster']['complete'] ?? false)
            && array_is_list($context['course_roster']['participants'] ?? []) && ! empty($context['course_roster']['participants'])
            && count($context['course_roster']['participants']) <= 200, 422, 'Durchführungstermin, Kursgruppe oder Prüfungskandidaten sind nicht eindeutig.');
        $names = [];
        foreach ($context['course_roster']['participants'] as $person) {
            $name = $this->personName($person);
            abort_if(isset($names[mb_strtolower($name)]), 422, 'Mindestens zwei Prüfungskandidaten haben denselben Ordnernamen. Bitte die Zuordnung klären.');
            $names[mb_strtolower($name)] = true;
        }
        $this->folderName($context);
    }

    private function assertUnreleased(TeachingCourseWork $work): void
    {
        $status = $work->fresh()->status ?? [];
        foreach (['assessment_json_imports', 'assessment_json_packages', 'evaluation_pdfs', 'dispatch_notifications', 'submission_checks', 'provision_events'] as $field) {
            abort_if(! empty($status[$field]), 409, 'Die Arbeit besitzt bereits Originalnachweise; eine neue Anlage ist gesperrt.');
        }
        abort_if(! empty($status['student_distribution']['published_at']), 409, 'Die Arbeit wurde bereits bereitgestellt.');
        foreach ($status['assignment_mail']['batches'] ?? [] as $batch) {
            if (($batch['transport'] ?? null) !== 'postmark') {
                continue;
            }
            $states = array_column($batch['recipients'] ?? [], 'state');
            abort_if(array_intersect($states, ['sending', 'uncertain']) !== [], 409,
                'Die Einlieferung von Aufgabenmails läuft oder ist noch ungeklärt. Bitte zuerst die Versandbelege prüfen.');
            abort_if(empty($batch['withdrawn_at']) && array_intersect($states, ['queued', 'accepted']) !== [], 409,
                'Die Aufgabe wird per E-Mail bereitgestellt. Bitte zuerst den Aufgabenversand im Bereich „Bereitstellung“ stoppen.');
        }
    }

    /** @param array<string, mixed> $person */
    private function personName(array $person): string
    {
        $name = trim(($person['last_name'] ?? '').' '.($person['first_name'] ?? ''));
        abort_unless(filled($person['last_name'] ?? null) && filled($person['first_name'] ?? null), 422, 'Ein Kandidatenname ist unvollständig.');

        return $this->safeName($name);
    }

    /** @param array<string, mixed> $context */
    private function folderName(array $context): string
    {
        return $this->safeName($context['work']['scheduled_date'].' – '.$context['course']['folder_group'].' – '.trim($context['work']['title']));
    }

    private function safeName(string $name): string
    {
        abort_unless($name !== '' && mb_strlen($name) <= 200 && ! preg_match('/[\/\\\\\x00-\x1f]/u', $name)
            && ! in_array($name, ['.', '..'], true) && rtrim($name, ' .') === $name, 422, 'Ein erforderlicher Ordnername ist ungültig.');

        return $name;
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $context @return array<string, mixed>|null */
    private function checkExisting(DropboxConnection $connection, array $module, int $workId, int $courseId, array $context, ?string $ownFolderId = null, ?array &$reusable = null, ?string $linkedStatus = null, ?string $resumeStatus = null, ?array $resumeTemplate = null): ?array
    {
        $roots = array_values(array_filter($this->children($connection, $module['id']), fn (array $entry): bool => mb_strtolower($entry['name']) === 'leistungsfeststellungen'));
        abort_if(count($roots) > 1 || ($roots !== [] && ($roots[0]['.tag'] ?? null) !== 'folder'), 409, 'Der Prüfungsüberordner ist nicht eindeutig.');
        if ($roots === []) {
            return null;
        }
        $root = $this->dropbox->metadata($connection, $roots[0]['id']);
        abort_unless(($root['id'] ?? null) === $roots[0]['id'] && dirname($root['path_lower']) === $module['path_lower'], 409, 'Der Prüfungsüberordner wurde verschoben.');
        foreach ($this->children($connection, $root['id']) as $entry) {
            abort_if(($entry['.tag'] ?? null) !== 'folder' && mb_strtolower($entry['name']) === mb_strtolower($this->folderName($context)),
                409, 'Am Prüfungsziel liegt eine Datei statt eines Ordners. Bitte den Bestand prüfen.');
            if (($entry['.tag'] ?? null) !== 'folder') {
                continue;
            }
            $status = $this->dropbox->readExerciseDocument($connection, $entry['id'], 'status.json');
            if ($status === null && $entry['id'] !== $ownFolderId
                && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} – /u', $entry['name']) === 1
                && substr($entry['name'], 0, 10) !== $context['work']['scheduled_date']
                && $this->children($connection, $entry['id']) === []) {
                continue;
            }
            $partial = $status !== null && $resumeStatus !== null && $this->matchesStatus($status['contents'], $resumeStatus) && $resumeTemplate !== null;
            if (($ownFolderId === null || $entry['id'] === $ownFolderId) && $entry['name'] === $this->folderName($context)
                && ($status === null || ($linkedStatus !== null && $status['contents'] === $linkedStatus) || $partial)) {
                abort_if($reusable !== null, 409, 'Mehrere Prüfungsordner passen zum selben Ziel. Bitte den Bestand prüfen.');
                $folder = $this->dropbox->metadata($connection, $entry['id']);
                abort_unless(($folder['id'] ?? null) === $entry['id'] && ($folder['name'] ?? null) === $entry['name']
                    && ($folder['path_lower'] ?? null) === $root['path_lower'].'/'.mb_strtolower($entry['name'])
                    && empty($folder['sharing_info']) && empty($root['sharing_info']) && empty($module['sharing_info']),
                    409, 'Der vorhandene Prüfungsordner ist verschoben, geteilt oder nicht eindeutig. Bitte den Bestand prüfen.');
                $children = $this->children($connection, $entry['id'], includeSharing: $partial);
                $treeFingerprint = null;
                if ($partial) {
                    $treeFingerprint = $this->validateResumeTree($connection, $folder, $context, $resumeTemplate, $children);
                } else {
                    abort_unless($status === null ? $children === [] : count($children) === 1 && ($children[0]['name'] ?? null) === 'status.json',
                        409, 'Der vorhandene Prüfungsordner ist nicht leer. Bitte den Bestand prüfen.');
                }
                $reusable = ['kind' => $status === null ? 'empty' : ($partial ? 'partial' : 'linked'), 'folder' => $folder, 'revision' => $status['rev'] ?? null];
                if ($partial) {
                    $reusable['tree_fingerprint'] = $treeFingerprint;
                }

                continue;
            }
            try {
                $record = $status ? json_decode($status['contents'], true, 32, JSON_THROW_ON_ERROR) : null;
                $unique = $status && $this->uniqueJsonKeys($status['contents']);
            } catch (JsonException) {
                $record = null;
                $unique = false;
            }
            abort_unless($unique && is_array($record) && ($record['schema_version'] ?? null) === 1
                && ($record['kind'] ?? null) === 'leistungsfeststellung.status' && is_bool($record['prepared'] ?? null)
                && is_int($record['schooltool']['work_id'] ?? null)
                && is_int($record['schooltool']['course_id'] ?? null), 409, 'Ein vorhandener Prüfungsordner ist nicht eindeutig zugeordnet. Bitte den Bestand zuerst prüfen.');
            if ($entry['id'] === $ownFolderId) {
                abort_unless($record['schooltool']['work_id'] === $workId && $record['schooltool']['course_id'] === $courseId
                    && $entry['name'] === $this->folderName($context), 409, 'Der begonnene Prüfungsordner ist nicht mehr eindeutig zugeordnet.');

                continue;
            }
            abort_if(($record['schooltool']['work_id'] === $workId && $record['schooltool']['course_id'] === $courseId)
                || mb_strtolower($entry['name']) === mb_strtolower($this->folderName($context)), 409,
                'Ein zugeordneter oder gleichnamiger Prüfungsordner ist bereits vorhanden. Bitte „Erneut prüfen“ verwenden.');
        }

        return $root;
    }

    /** @param array<string, mixed> $folder @param array<string, mixed> $context @param array<string, mixed> $template @param list<array<string, mixed>> $children */
    private function validateResumeTree(DropboxConnection $connection, array $folder, array $context, array $template, array $children): string
    {
        $assignment = $template['documents']['assignment'];
        $allowedFiles = ['Beurteilung.md' => $template['documents']['criteria_markdown']['bytes'],
            'Beurteilung.pdf' => $template['documents']['criteria']['bytes'], $assignment['name'] => $assignment['bytes']];
        $seen = [];
        $identities = [];
        foreach ($children as $entry) {
            $name = $entry['name'] ?? '';
            abort_if(isset($seen[mb_strtolower($name)]) || ! empty($entry['sharing_info']) || ($entry['has_explicit_shared_members'] ?? false), 409, 'Der bestehende Prüfungsbestand ist nicht eindeutig oder geteilt.');
            $seen[mb_strtolower($name)] = true;
            $identities[] = [$folder['id'], $entry['id'], $name, $entry['.tag'] ?? null];
            if ($name === 'status.json' && ($entry['.tag'] ?? null) === 'file') {
                continue;
            }
            if (array_key_exists($name, $allowedFiles) && ($entry['.tag'] ?? null) === 'file') {
                $existing = $this->dropbox->readExerciseDocument($connection, $folder['id'], $name);
                abort_unless($existing && hash_equals(hash('sha256', $allowedFiles[$name]), hash('sha256', $existing['contents'])), 409, 'Eine vorhandene Prüfungsdatei weicht von der Vorlage ab.');
                $identities[] = [$entry['id'], $existing['rev']];

                continue;
            }
            abort_unless($name === 'Kandidaten' && ($entry['.tag'] ?? null) === 'folder', 409, 'Der bestehende Prüfungsbestand enthält fremde Einträge.');
            $candidates = $this->dropbox->metadata($connection, $entry['id']);
            abort_unless(($candidates['id'] ?? null) === $entry['id'] && ($candidates['name'] ?? null) === $name
                && dirname($candidates['path_lower']) === $folder['path_lower'] && empty($candidates['sharing_info']), 409, 'Der Kandidatenordner ist verändert oder geteilt.');
            $expected = array_map(fn (array $person): string => $this->personName($person), $context['course_roster']['participants']);
            $seenPeople = [];
            foreach ($this->children($connection, $candidates['id'], includeSharing: true) as $personEntry) {
                $identities[] = [$candidates['id'], $personEntry['id'], $personEntry['name'], $personEntry['.tag'] ?? null];
                $personKey = mb_strtolower($personEntry['name'] ?? '');
                abort_if(isset($seenPeople[$personKey]), 409, 'Der Kandidatenbestand enthält doppelte Einträge.');
                $seenPeople[$personKey] = true;
                abort_unless(($personEntry['.tag'] ?? null) === 'folder' && in_array($personEntry['name'] ?? null, $expected, true)
                    && empty($personEntry['sharing_info']) && ! ($personEntry['has_explicit_shared_members'] ?? false), 409, 'Der Kandidatenbestand enthält fremde oder geteilte Einträge.');
                $person = $this->dropbox->metadata($connection, $personEntry['id']);
                abort_unless(($person['id'] ?? null) === $personEntry['id'] && ($person['name'] ?? null) === $personEntry['name']
                    && dirname($person['path_lower']) === $candidates['path_lower'] && empty($person['sharing_info']), 409, 'Ein Kandidatenordner ist verändert oder geteilt.');
                $seenSections = [];
                foreach ($this->children($connection, $person['id'], includeSharing: true) as $sectionEntry) {
                    $identities[] = [$person['id'], $sectionEntry['id'], $sectionEntry['name'], $sectionEntry['.tag'] ?? null];
                    $sectionKey = mb_strtolower($sectionEntry['name'] ?? '');
                    abort_if(isset($seenSections[$sectionKey]), 409, 'Ein Kandidatenordner enthält doppelte Einträge.');
                    $seenSections[$sectionKey] = true;
                    abort_unless(($sectionEntry['.tag'] ?? null) === 'folder' && in_array($sectionEntry['name'] ?? null, ['Angaben', 'Abgaben', 'Auswertung'], true)
                        && empty($sectionEntry['sharing_info']) && ! ($sectionEntry['has_explicit_shared_members'] ?? false), 409, 'Ein Kandidatenordner enthält fremde oder geteilte Einträge.');
                    $section = $this->dropbox->metadata($connection, $sectionEntry['id']);
                    abort_unless(($section['id'] ?? null) === $sectionEntry['id'] && ($section['name'] ?? null) === $sectionEntry['name']
                        && dirname($section['path_lower']) === $person['path_lower'] && empty($section['sharing_info']), 409, 'Ein Unterordner ist verändert oder geteilt.');
                    $sectionFiles = $this->children($connection, $section['id'], includeSharing: true);
                    abort_if(count($sectionFiles) > 1, 409, 'Ein Unterordner enthält mehr als eine Datei.');
                    foreach ($sectionFiles as $file) {
                        abort_unless($section['name'] === 'Angaben' && ($file['.tag'] ?? null) === 'file'
                            && ($file['name'] ?? null) === $assignment['name'] && empty($file['sharing_info'])
                            && ! ($file['has_explicit_shared_members'] ?? false), 409, 'Ein Unterordner enthält fremde oder geteilte Einträge.');
                        $existing = $this->dropbox->readExerciseDocument($connection, $section['id'], $file['name']);
                        abort_unless($existing && hash_equals(hash('sha256', $assignment['bytes']), hash('sha256', $existing['contents'])), 409, 'Ein vorhandenes Aufgabenblatt weicht von der Vorlage ab.');
                        $identities[] = [$section['id'], $file['id'], $file['name'], $existing['rev']];
                    }
                }
            }
        }
        abort_unless(isset($seen['status.json']), 409, 'Die Statusdatei fehlt.');
        usort($identities, fn (array $a, array $b): int => strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));

        return SchooltoolAssessmentJson::digest(['entries' => $identities]);
    }

    private function matchesStatus(string $actual, string $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }
        try {
            $record = json_decode($actual, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($record) || ! is_bool($record['prepared'] ?? null) || $this->json($record) !== $actual
                || ! TeachingAssessmentProgress::valid($record['progress'] ?? null, $record['prepared'])) {
                return false;
            }
            unset($record['progress']);

            return $this->json($record) === $expected;
        } catch (JsonException) {
            return false;
        }
    }

    private function uniqueJsonKeys(string $json): bool
    {
        preg_match_all('/"(?:\\\\.|[^"\\\\])*"|[{}\[\]:,]/s', $json, $tokens);
        $stack = [];
        foreach ($tokens[0] as $index => $token) {
            if ($token === '{' || $token === '[') {
                $stack[] = ['object' => $token === '{', 'keys' => []];
            } elseif ($token === '}' || $token === ']') {
                array_pop($stack);
            } elseif (str_starts_with($token, '"') && ($tokens[0][$index + 1] ?? null) === ':' && $stack !== []) {
                $last = count($stack) - 1;
                $key = json_decode($token, true, 2, JSON_THROW_ON_ERROR);
                if (! $stack[$last]['object'] || array_key_exists($key, $stack[$last]['keys'])) {
                    return false;
                }
                $stack[$last]['keys'][$key] = true;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $parent @return array<string, mixed> */
    private function ensureFolder(DropboxConnection $connection, array $parent, string $name, bool $readOnly, ?array $knownChildren = null): array
    {
        $matches = array_values(array_filter($knownChildren ?? $this->children($connection, $parent['id']), fn (array $entry): bool => mb_strtolower($entry['name']) === mb_strtolower($name)));
        abort_if(count($matches) > 1 || ($matches !== [] && (($matches[0]['.tag'] ?? null) !== 'folder' || $matches[0]['name'] !== $name)),
            409, 'Ein vorhandener Zielordner ist nicht eindeutig.');
        if ($matches !== []) {
            $folder = $this->dropbox->metadata($connection, $matches[0]['id']);
            abort_unless(($folder['name'] ?? null) === $name && dirname($folder['path_lower']) === $parent['path_lower'], 409, 'Ein Zielordner wurde verschoben.');

            return $folder;
        }
        abort_if($readOnly, 409, 'Ein bereits bestätigter Prüfungsordner ist unvollständig.');

        return $this->createLockedFolder($connection, $parent['id'], $name, $parent['path_lower']);
    }

    private function ensureFile(DropboxConnection $connection, string $folder, string $name, string $bytes, bool $readOnly): void
    {
        $existing = $this->dropbox->readExerciseDocument($connection, $folder, $name);
        if ($existing) {
            abort_unless(hash_equals(hash('sha256', $bytes), hash('sha256', $existing['contents'])), 409,
                'Eine vorhandene Datei weicht von der bestätigten Vorschau ab.');

            return;
        }
        abort_if($readOnly, 409, 'Eine Datei im bereits bestätigten Prüfungsordner fehlt.');
        $this->write($connection, $folder, $name, $bytes);
    }

    private function assertInitialStatusOnly(DropboxConnection $connection, string $folder): void
    {
        $children = $this->children($connection, $folder);
        abort_unless(count($children) === 1 && ($children[0]['.tag'] ?? null) === 'file'
            && ($children[0]['name'] ?? null) === 'status.json', 409, 'Der Prüfungsordner wurde während der Anlage verändert.');
    }

    /** @param \Closure(\Closure(): void): void $operation */
    public function withModuleLock(string $moduleId, \Closure $operation): void
    {
        [$guard, $lock] = $this->acquireModuleLock($moduleId);
        try {
            $operation($this->refreshModuleLock(...));
        } finally {
            $this->releaseModuleLock($guard, $lock);
        }
    }

    /** @return array{0: resource, 1: Lock} */
    private function acquireModuleLock(string $moduleId): array
    {
        $name = 'teaching:direct-assessment:'.hash('sha256', $moduleId);
        $directory = storage_path('framework/cache/direct-assessment-locks');
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            abort(503, 'Die sichere Anlagesperre ist nicht verfügbar.');
        }
        $guard = @fopen($directory.DIRECTORY_SEPARATOR.hash('sha256', $name).'.lock', 'c');
        if (! is_resource($guard)) {
            abort(503, 'Die sichere Anlagesperre ist nicht verfügbar.');
        }
        if (! flock($guard, LOCK_EX | LOCK_NB)) {
            fclose($guard);
            abort(409, 'Für dieses Lernmodul läuft bereits eine Anlage. Bitte danach erneut prüfen.');
        }
        try {
            $lock = Cache::lock($name, self::LOCK_LEASE_SECONDS);
            abort_unless($lock instanceof FileLock || $lock instanceof DatabaseLock || $lock instanceof RedisLock
                || (app()->runningUnitTests() && $lock instanceof ArrayLock), 503,
                'Der konfigurierte Cache unterstützt die sichere Anlagesperre nicht.');
            abort_unless($lock->get(), 409, 'Für dieses Lernmodul läuft bereits eine Anlage. Bitte danach erneut prüfen.');
            $this->activeModuleLock = $lock;
            $this->refreshModuleLock();

            return [$guard, $lock];
        } catch (Throwable $exception) {
            $this->activeModuleLock = null;
            flock($guard, LOCK_UN);
            fclose($guard);
            throw $exception;
        }
    }

    /** @param resource $guard */
    private function releaseModuleLock($guard, Lock $lock): void
    {
        $this->activeModuleLock = null;
        try {
            if ($this->renewLock($lock)) {
                $lock->release();
            }
        } catch (Throwable $exception) {
            logger()->warning('Direct assessment lock release failed', ['exception_class' => get_class($exception)]);
        } finally {
            flock($guard, LOCK_UN);
            fclose($guard);
        }
    }

    private function refreshModuleLock(): void
    {
        try {
            $owned = $this->activeModuleLock !== null && $this->renewLock($this->activeModuleLock);
        } catch (Throwable) {
            $owned = false;
        }
        abort_unless($owned, 409, 'Die Anlagesperre konnte nicht sicher erneuert werden. Bitte den Stand prüfen und erneut versuchen.');
    }

    private function renewLock(Lock $lock): bool
    {
        // MySQL can report zero changed rows when the expiration is set twice in the same second.
        return $lock->refresh(self::LOCK_LEASE_SECONDS)
            || ($lock instanceof DatabaseLock && $lock->refresh(self::LOCK_LEASE_SECONDS + 1));
    }

    /** @return array<string, mixed> */
    private function createLockedFolder(DropboxConnection $connection, string $parentId, string $name, string $expectedParentPath): array
    {
        $this->refreshModuleLock();

        return $this->dropbox->createExerciseChildFolder($connection, $parentId, $name, $expectedParentPath);
    }

    /** @return list<array<string, mixed>> */
    private function children(DropboxConnection $connection, string $id, bool $includeSharing = false): array
    {
        $data = $this->dropbox->readExerciseInventory($connection, $id, $includeSharing)->json();
        abort_unless(is_array($data['entries'] ?? null) && ($data['has_more'] ?? true) === false, 422, 'Der Zielbestand konnte nicht vollständig geprüft werden.');
        foreach ($data['entries'] as $entry) {
            abort_unless(is_array($entry) && is_string($entry['name'] ?? null) && is_string($entry['id'] ?? null), 422, 'Der Zielbestand ist ungültig.');
        }

        return $data['entries'];
    }

    private function write(DropboxConnection $connection, string $folder, string $name, string $bytes, ?string $revision = null): string
    {
        $this->refreshModuleLock();
        $saved = $this->dropbox->writeExerciseDocument($connection, $folder, $name, $bytes, $revision);
        $this->verifyFile($connection, $folder, $name, $bytes, $saved);

        return $saved;
    }

    private function verifyFile(DropboxConnection $connection, string $folder, string $name, string $bytes, ?string $revision = null): void
    {
        $actual = $this->dropbox->readExerciseDocument($connection, $folder, $name);
        abort_unless($actual && ($revision === null || $actual['rev'] === $revision)
            && hash_equals(hash('sha256', $bytes), hash('sha256', $actual['contents'])), 409, 'Eine gespeicherte Datei konnte nicht bestätigt werden.');
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function status(TeachingCourseWork $work, array $context, float $points, bool $prepared): array
    {
        return ['schema_version' => 1, 'kind' => 'leistungsfeststellung.status', 'schooltool' => ['work_id' => $work->id, 'course_id' => $work->teaching_course_id],
            'folder_group' => $context['course']['folder_group'], 'scheduled_date' => $context['work']['scheduled_date'], 'prepared' => $prepared,
            'components' => ['aufgabenblatt' => ['included' => true, 'points' => $points], 'multiple_choice' => ['included' => false, 'points' => null]], 'total_points' => $points];
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function initialStatus(TeachingCourseWork $work, array $context): array
    {
        return ['schema_version' => 1, 'kind' => 'leistungsfeststellung.status', 'schooltool' => ['work_id' => $work->id, 'course_id' => $work->teaching_course_id],
            'folder_group' => $context['course']['folder_group'], 'scheduled_date' => $context['work']['scheduled_date'], 'prepared' => false,
            'components' => ['aufgabenblatt' => ['included' => false, 'points' => null], 'multiple_choice' => ['included' => false, 'points' => null]],
            'total_points' => null];
    }

    /** @param array<string, mixed> $context */
    private function digest(array $context): string
    {
        unset($context['exported_at']);
        if (is_array($context['course']['classes'] ?? null)) {
            sort($context['course']['classes'], SORT_STRING);
        }

        return SchooltoolAssessmentJson::digest($context);
    }

    /** @param array<string, mixed> $template @return array<string, mixed> */
    private function templateIdentity(array $template): array
    {
        return ['points' => $template['points'], 'folder' => $template['folder'], 'documents' => array_map(
            fn (array $document): array => array_diff_key($document, ['bytes' => true]), $template['documents'])];
    }

    private function key(TeachingCourseWork $work, User $actor, string $id): string
    {
        return 'teaching:direct-assessment:'.$actor->id.':'.$work->id.':'.$id;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
