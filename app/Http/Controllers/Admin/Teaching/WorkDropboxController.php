<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Admin\ConnectionStatusController;
use App\Http\Controllers\Admin\MultipleChoiceController;
use App\Http\Controllers\Controller;
use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use App\Services\DropboxFolderContents;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingSimulation;
use App\Services\TeachingWorkDeadlineSync;
use App\Services\TeachingWorkDispatchImport;
use App\Services\TeachingWorkFolderImport;
use App\Services\TeachingWorkFolderVerification;
use App\Services\TeachingWorkJsonImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkDropboxController extends Controller
{
    public function refresh(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox, TeachingWorkDeadlineSync $deadlines): JsonResponse
    {
        $actor = $this->actor($course_work);
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'finish_until_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'finish_until_time' => ['present', 'nullable', 'date_format:H:i'],
            'expected' => ['required', 'array:date,time'],
            'expected.date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'expected.time' => ['present', 'nullable', 'date_format:H:i'],
        ]);
        $connection = $dropbox->connection($actor);
        $modules = app(DropboxModuleLinks::class);
        $link = $modules->current($course_work);
        abort_unless($link && $link->dropbox_connection_id === $connection->id && $link->account_id === $connection->account_id, 409,
            'Die Modulverbindung muss zuerst bestätigt werden.');
        $synchronized = DB::transaction(function () use ($course_work, $data, $modules, $connection, $link, $deadlines): array {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            abort_unless($work->teaching_course_id === $course_work->teaching_course_id
                && $modules->current($work)?->getAttributes() === $link->getAttributes(), 409, 'Die Arbeitszuordnung wurde inzwischen geändert.');
            abort_unless($work->finish_until_date?->format('Y-m-d') === $data['expected']['date']
                && ($work->finish_until_time ?: null) === $data['expected']['time'], 409, 'Die Frist wurde inzwischen geändert. Bitte den Arbeiten-Dialog neu öffnen.');
            $exercise = $modules->exercise($connection, $work, $link, $data['date'] ?? null);
            if ($exercise['status'] === 'unknown') {
                $exercise = $modules->exercise($connection, $work, $link, $data['date'] ?? null, refresh: true);
            }
            abort_unless(in_array($exercise['status'], ['found', 'missing'], true), 409, $exercise['message'] ?? 'Die Leistungsfeststellung ist nicht eindeutig.');
            $work->finish_until_date = $data['finish_until_date'];
            $work->finish_until_time = $data['finish_until_time'];
            $deadlines->metadata($work, ['MitgeteilteAbgabefrist' => $work->communicatedDeadline()['at'] ?? null,
                'Schooltool_Fristdaten' => ['communicated_deadline' => $work->communicatedDeadline()]]);
            $result = $exercise['status'] === 'found' ? $deadlines->sync($connection, $work, $exercise['folder'])
                : ['changed' => false, 'message' => 'Frist gespeichert; die Leistungsfeststellung wurde noch nicht erstellt.'];
            if ($work->isDirty(['finish_until_date', 'finish_until_time'])) {
                $work->save();
                app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
            }

            return $result;
        });
        $course_work->refresh();
        $request->merge(['include_exercise' => true, 'refresh' => true]);
        $response = $this->show($course_work, $dropbox);
        $body = $response->getData(true);
        $body['deadline_sync'] = $synchronized;
        $body['saved_work'] = app(TeachingCourseWorkEntrySyncService::class)->serializeWork($course_work->load('teachingCourseWorkGroupStudents'));
        $response->setData($body);

        return $response;
    }

    public function show(TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
        $configured = $dropbox->configured();
        $installed = $dropbox->installed();
        $connection = $installed ? DropboxConnection::query()->where('user_id', $actor->id)->first() : null;
        $connected = $configured && $connection && ! $connection->revoked_at;
        $folder = $connected ? TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
            ->where('teaching_course_work_id', $course_work->id)->first() : null;
        $suggested = $folder;
        $plausibility = $suggested ? $dropbox->folderPlausibility($course_work, $suggested->folder_name) : null;
        $hasImport = filled($course_work->status['folder_imported_at'] ?? null);
        $canPreview = $course_work->automationEnabled() && $connected && $folder && $plausibility['status'] !== 'mismatch';
        $folderData = $folder ? ['id' => $folder->folder_id, 'name' => $folder->folder_name] : null;
        $modules = app(DropboxModuleLinks::class);
        $module = $connected ? $modules->current($course_work) : null;
        $ownModule = $module && $module->dropbox_connection_id === $connection->id && $module->account_id === $connection->account_id;
        $exercise = null;
        if (request()->boolean('include_exercise')) {
            $data = request()->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'refresh' => ['sometimes', 'boolean']]);
            $exercise = $ownModule ? $modules->exercise($connection, $course_work, $module, $data['date'] ?? null, refresh: request()->boolean('refresh'))
                : ['status' => 'missing', 'folder' => null];
        }
        if ($folder && request()->boolean('include_folder_path')) {
            $metadata = $dropbox->metadata($connection, $folder->folder_id);
            $path = $metadata['path_display'] ?? $metadata['path_lower'];
            abort_unless($metadata['id'] === $folder->folder_id && is_string($path)
                && str_starts_with($path, '/') && ! preg_match('/[\x00-\x1f]/u', $path), 422, 'Dropbox-Ordnerpfad konnte nicht eindeutig bestätigt werden.');
            $folderData['path'] = $path;
        }
        $legacyFolders = $folder && ! $module && request()->boolean('include_folder_path')
            ? $dropbox->legacyFolderContext($connection, $folder->folder_id) : null;

        return response()->json(['configured' => $configured, 'installed' => $installed, 'connected' => (bool) $connected,
            'module_linking_available' => $modules->installed(),
            'link_version' => $connected ? $modules->assignmentVersion($connection, $course_work, $module, $folder) : null,
            'module_link' => $ownModule ? ['module_id' => $module->module_id, 'folder' => ['id' => $module->folder_id,
                'name' => $module->folder_name, 'path' => $module->folder_path]] : null,
            'module_conflict' => (bool) ($module && ! $ownModule),
            'exercise' => $exercise,
            'folder' => $folderData,
            'legacy_folders' => $legacyFolders,
            'suggested_folder' => $suggested ? ['id' => $suggested->folder_id, 'name' => $suggested->folder_name] : null,
            'folder_plausibility' => $plausibility,
            'has_import' => $hasImport,
            'can_preview' => (bool) $canPreview,
            'can_quick_import' => (bool) ($canPreview && $hasImport),
            'message' => ! $configured || ! $installed ? 'Dropbox ist noch nicht eingerichtet. Bitte Importieren verwenden.'
                : (! $connected ? 'Dropbox verbinden, um Quick-Import zu verwenden.'
                    : (! $folder ? 'Für diese Arbeit einen Dropbox-Ordner zuordnen.'
                        : ($plausibility['status'] === 'mismatch' ? $plausibility['message']
                            : (! $hasImport ? 'Quick-Import erst nach dem ersten erfolgreich übernommenen Import dieser Arbeit.' : 'Dropbox-Ordner erneut lesen und Vorschau öffnen.'))))])->header('Cache-Control', 'private, no-store');
    }

    public function connect(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $state = bin2hex(random_bytes(32));
        $url = $dropbox->authorizationUrl($state);
        $request->session()->put('teaching_dropbox_oauth', ['state' => $state, 'user_id' => $actor->id,
            'work_id' => $course_work->id, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return response()->json(['url' => $url]);
    }

    public function callback(Request $request, DropboxWorkImport $dropbox): RedirectResponse
    {
        $pending = $request->session()->pull('teaching_dropbox_oauth');
        abort_unless(is_array($pending) && is_string($request->query('state'))
            && hash_equals($pending['state'], $request->query('state'))
            && $pending['user_id'] === $request->user()?->id && $pending['expires_at'] >= now()->timestamp, 403, 'Dropbox-Verbindungsanfrage ist ungültig oder abgelaufen.');
        if (isset($pending['mc_test_id'])) {
            return app(MultipleChoiceController::class)->completeFolderConnection($request, $dropbox, $pending);
        }
        if (! empty($pending['connection_status'])) {
            return app(ConnectionStatusController::class)->complete($request, $dropbox);
        }
        $work = TeachingCourseWork::query()->findOrFail($pending['work_id']);
        $actor = $this->actor($work);
        abort_unless($work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $returnPath = '/admin/teaching?'.http_build_query(['panel' => 'table', 'course' => $work->teaching_course_id,
            'dropbox_work' => $work->id, 'dropbox' => $request->query('error') ? 'cancelled' : 'connected']);
        if ($request->query('error')) {
            return redirect($returnPath);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:2048']]);
        try {
            $dropbox->connect($actor, $data['code']);
        } catch (ValidationException) {
            return redirect(str_replace('dropbox=connected', 'dropbox=failed', $returnPath));
        }

        return redirect($returnPath);
    }

    public function folders(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse|Response
    {
        $actor = $this->actor($course_work);
        $data = $request->validate(['folder_id' => ['nullable', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/'],
            'cursor' => ['nullable', 'string', 'max:4096'], 'suggest' => ['sometimes', 'boolean'],
            'contents' => ['sometimes', 'boolean'], 'exercise' => ['sometimes', 'boolean'], 'module' => ['sometimes', 'boolean'],
            'section' => ['nullable', 'string', 'in:Angaben,Bewertungskriterien,Abgaben,Auswertungen'],
            'pdf' => ['sometimes', 'boolean'],
            'file_id' => ['required_if:pdf,1', 'nullable', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/'],
            'date' => ['nullable', 'date_format:Y-m-d']]);
        if ($request->boolean('contents')) {
            abort_if($request->boolean('module') && $request->boolean('exercise'), 422, 'Bitte entweder Lernmodul oder Leistungsfeststellung öffnen.');
            abort_if($request->boolean('pdf') && (! $request->boolean('exercise') || ! filled($data['section'] ?? null)
                || filled($data['folder_id'] ?? null) || filled($data['cursor'] ?? null)), 422, 'PDFs nur direkt aus einem bestätigten Prüfungsbereich öffnen.');
            abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
            $connection = $dropbox->connection($actor);
            $modules = app(DropboxModuleLinks::class);
            $module = $modules->current($course_work);
            $linked = TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
                ->where('teaching_course_work_id', $course_work->id)->first();
            $version = $modules->assignmentVersion($connection, $course_work, $module, $linked);
            $excludedFolders = [];
            if ($module && $module->dropbox_connection_id === $connection->id && $module->account_id === $connection->account_id && $request->boolean('module')) {
                $confirmed = $modules->inspect($connection, $module->handshake_file_id);
                abort_unless($confirmed['folder_id'] === $module->folder_id
                    && strcasecmp($confirmed['module_id'], $module->module_id) === 0, 409, 'Die Modulverbindung hat sich geändert.');
                $rootId = $confirmed['folder_id'];
                $excludedFolders = ['Leistungsfeststellung'];
            } elseif ($module && $module->dropbox_connection_id === $connection->id && $module->account_id === $connection->account_id) {
                $exercise = $modules->exercise($connection, $course_work, $module, $data['date'] ?? null, initial: false);
                abort_unless($exercise['status'] !== 'unknown' && (! $request->boolean('exercise') || $exercise['status'] === 'found'),
                    409, 'Die Leistungsfeststellung konnte nicht eindeutig bestätigt werden.');
                if ($exercise['status'] === 'found') {
                    $rootId = $exercise['folder']['id'];
                } else {
                    $confirmed = $modules->inspect($connection, $module->handshake_file_id);
                    abort_unless($confirmed['folder_id'] === $module->folder_id
                        && strcasecmp($confirmed['module_id'], $module->module_id) === 0, 409, 'Die Modulverbindung hat sich geändert.');
                    $rootId = $confirmed['folder_id'];
                    $excludedFolders = ['Leistungsfeststellung'];
                }
            } else {
                abort_unless($linked, 409, 'Kein Arbeitsordner verknüpft.');
                $rootId = $linked->folder_id;
                if ($request->boolean('module') || $request->boolean('exercise')) {
                    $legacyFolders = $dropbox->legacyFolderContext($connection, $linked->folder_id);
                    abort_unless(! $request->boolean('exercise') || $legacyFolders['exercise'], 409,
                        'Der Leistungsfeststellungsordner ist noch nicht eindeutig zugeordnet. Bitte den konkreten Gruppenordner verknüpfen.');
                    $rootId = $legacyFolders[$request->boolean('exercise') ? 'exercise' : 'module']['id'];
                    if ($request->boolean('module')) {
                        $excludedFolders = ['Leistungsfeststellung'];
                    }
                }
            }
            if (filled($data['section'] ?? null)) {
                abort_unless($request->boolean('exercise'), 422, 'Prüfungsunterlagen erfordern eine bestätigte Leistungsfeststellung.');
                $rootContents = app(DropboxFolderContents::class)->read($connection, $rootId);
                $sections = array_values(array_filter($rootContents['entries'], fn (array $entry): bool => $entry['type'] === 'folder'
                    && $entry['name'] === $data['section']));
                abort_unless(count($sections) === 1 && $rootContents['cursor'] === null, 404, 'Dieser Bereich ist im Prüfungsordner noch nicht verfügbar.');
                $rootId = $sections[0]['id'];
            }
            $contents = app(DropboxFolderContents::class)->read($connection, $rootId, $data['folder_id'] ?? null, $data['cursor'] ?? null, $excludedFolders);
            if ($request->boolean('pdf')) {
                abort_unless(collect($contents['entries'])->contains(fn (array $entry): bool => $entry['type'] === 'file'
                    && $entry['id'] === $data['file_id'] && preg_match('/\.pdf\z/i', $entry['name'])), 404, 'Die PDF gehört nicht zu diesem Prüfungsbereich.');
                $pdf = app(DropboxFolderContents::class)->pdf($connection, $rootId, $data['file_id']);
            }
            if (isset($rootContents)) {
                $scopePath = mb_strtolower($rootContents['root']['path']);
                abort_unless(dirname($dropbox->metadata($connection, $rootId)['path_lower']) === $scopePath
                    && $dropbox->metadata($connection, $rootContents['root']['id'])['path_lower'] === $scopePath,
                    409, 'Der Prüfungsbereich wurde während des Lesens verschoben. Bitte erneut öffnen.');
            }
            if (isset($legacyFolders['exercise']) && $legacyFolders['exercise']) {
                $after = $dropbox->metadata($connection, $linked->folder_id);
                abort_unless(($after['path_display'] ?? $after['path_lower']) === $legacyFolders['exercise']['path'],
                    409, 'Der Leistungsfeststellungsordner wurde während des Lesens verschoben. Bitte erneut öffnen.');
            }
            $currentLinked = TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
                ->where('teaching_course_work_id', $course_work->id)->first();
            abort_unless($version === $modules->assignmentVersion($connection, $course_work, $modules->current($course_work), $currentLinked)
                && $connection->fresh()?->account_id === $connection->account_id && ! $connection->fresh()?->revoked_at,
                409, 'Die Verbindung wurde während des Lesens geändert. Bitte erneut öffnen.');

            if (isset($pdf)) {
                return response($pdf['bytes'])->withHeaders(['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline',
                    'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
            }

            return response()->json($contents + ['link_version' => $version])->header('Cache-Control', 'private, no-store');
        }
        if ($request->boolean('suggest')) {
            return response()->json($dropbox->suggestFolders($dropbox->connection($actor), $course_work));
        }

        return response()->json($dropbox->folders($dropbox->connection($actor), $data['folder_id'] ?? '', $data['cursor'] ?? null));
    }

    public function storeFolder(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        if ($request->has('unlink')) {
            $data = $request->validate(['unlink' => ['required', 'boolean', 'accepted'],
                'link_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']], [
                    'link_version.required' => 'Bitte die aktuelle Verbindung vor dem Trennen erneut laden.',
                    'link_version.regex' => 'Die Bestätigung der aktuellen Verbindung ist ungültig.',
                ]);
            app(DropboxModuleLinks::class)->unlink($dropbox->connection($actor), $course_work, $data['link_version']);

            return $this->show($course_work, $dropbox);
        }
        if ($request->has('handshake')) {
            $data = $request->validate(['handshake' => ['required', 'string', 'max:4096'],
                'filename' => ['required', 'string', 'regex:/\Ahandshake\.json\z/i'],
                'resolve' => ['sometimes', 'boolean'], 'proof' => [$request->boolean('resolve') ? 'nullable' : 'required', 'string', 'max:8192']], [
                    'filename.required' => 'Bitte die Datei handshake.json auswählen.',
                    'filename.regex' => 'Zulässig ist ausschließlich die Datei handshake.json.',
                    'handshake.required' => 'Die handshake.json ist leer. Bitte eine gültige Handshake-Datei auswählen.',
                    'handshake.string' => 'Die handshake.json konnte nicht als Text gelesen werden.',
                    'handshake.max' => 'Die handshake.json darf höchstens 4 KB groß sein.',
                    'resolve.boolean' => 'Die Anfrage zur Online-Prüfung ist ungültig.',
                    'proof.required' => 'Bitte die Online-Datei vor dem Verknüpfen erneut prüfen.',
                ]);
            $connection = $dropbox->connection($actor);
            $modules = app(DropboxModuleLinks::class);
            if ($request->boolean('resolve')) {
                return response()->json($modules->resolve($connection, $course_work, $data['handshake']));
            }
            $modules->store($connection, $course_work, $data['handshake'], $data['proof']);

            return $this->show($course_work, $dropbox);
        }
        $data = $request->validate(['folder_id' => ['required', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/'],
            'verify' => ['sometimes', 'boolean'], 'folder_proof' => ['nullable', 'string', 'max:8192']]);
        $connection = $dropbox->connection($actor);
        $verification = app(TeachingWorkFolderVerification::class);
        if ($request->boolean('verify')) {
            return response()->json($verification->preview($connection, $course_work, $data['folder_id']))->header('Cache-Control', 'private, no-store');
        }
        if ($verification->required($course_work)) {
            abort_unless(filled($data['folder_proof'] ?? null), 409, 'Vor dem Verknüpfen zuerst die vorhandenen Originalnachweise prüfen.');
            $verification->store($connection, $course_work, $data['folder_id'], $data['folder_proof']);

            return $this->show($course_work, $dropbox);
        }
        $folder = $dropbox->metadata($connection, $data['folder_id']);
        $plausibility = $dropbox->folderPlausibility($course_work, $folder['name']);
        if ($plausibility['status'] === 'mismatch') {
            throw ValidationException::withMessages(['dropbox' => $plausibility['message']]);
        }
        TeachingWorkDropboxFolder::query()->updateOrCreate(['dropbox_connection_id' => $connection->id,
            'teaching_course_work_id' => $course_work->id], ['folder_id' => $folder['id'], 'folder_name' => $folder['name']]);

        return $this->show($course_work, $dropbox);
    }

    public function disconnect(TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
        $dropbox->requireReady();
        DropboxConnection::query()->where('user_id', $actor->id)->delete();

        return $this->show($course_work, $dropbox);
    }

    public function import(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
        abort_unless($course_work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $data = $request->validate(['apply' => ['sometimes', 'boolean'], 'hash' => ['required_if:apply,1', 'nullable', 'string', 'size:64'],
            'results_only' => ['sometimes', 'boolean'], 'simulation' => ['sometimes', 'boolean'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $resultsOnly = (bool) ($data['results_only'] ?? false);
        $simulation = (bool) ($data['simulation'] ?? false);
        abort_if($simulation && ! $resultsOnly, 422);

        $consume = function (array $parameters, array $uploads) use ($request, $course_work, $data): JsonResponse {
            $importRequest = Request::createFrom($request);
            $importRequest->replace($parameters + $data);
            $importRequest->files->replace($uploads);
            $importRequest->server->set('CONTENT_LENGTH', 0);

            return app(CourseWorkController::class)->importJson($importRequest, $course_work, app(TeachingWorkJsonImport::class),
                app(TeachingWorkFolderImport::class), app(TeachingWorkDispatchImport::class));
        };
        if ($simulation) {
            $ids = array_values(array_unique(array_merge(...array_column(app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($course_work), 'student_ids'))));

            return app(TeachingSimulation::class)->withResultFiles($course_work, $actor->id, $ids, $consume);
        }

        return $dropbox->withImportFiles($actor, $course_work, $consume, $resultsOnly, $data['date'] ?? null);
    }

    private function actor(TeachingCourseWork $work): User
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);

        return $actor;
    }
}
