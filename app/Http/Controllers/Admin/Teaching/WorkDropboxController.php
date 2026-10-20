<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Admin\ConnectionStatusController;
use App\Http\Controllers\Admin\MultipleChoiceController;
use App\Http\Controllers\Controller;
use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingWorkDispatchImport;
use App\Services\TeachingWorkFolderImport;
use App\Services\TeachingWorkJsonImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkDropboxController extends Controller
{
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
            $data = request()->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
            $exercise = $ownModule ? $modules->exercise($connection, $course_work, $module, $data['date'] ?? null)
                : ['status' => 'missing', 'folder' => null];
        }
        if ($folder && request()->boolean('include_folder_path')) {
            $metadata = $dropbox->metadata($connection, $folder->folder_id);
            $path = $metadata['path_display'] ?? $metadata['path_lower'];
            abort_unless($metadata['id'] === $folder->folder_id && is_string($path)
                && str_starts_with($path, '/') && ! preg_match('/[\x00-\x1f]/u', $path), 422, 'Dropbox-Ordnerpfad konnte nicht eindeutig bestätigt werden.');
            $folderData['path'] = $path;
        }

        return response()->json(['configured' => $configured, 'installed' => $installed, 'connected' => (bool) $connected,
            'module_linking_available' => $modules->installed(),
            'link_version' => $connected ? $modules->assignmentVersion($connection, $course_work, $module, $folder) : null,
            'module_link' => $ownModule ? ['module_id' => $module->module_id, 'folder' => ['id' => $module->folder_id,
                'name' => $module->folder_name, 'path' => $module->folder_path]] : null,
            'module_conflict' => (bool) ($module && ! $ownModule),
            'exercise' => $exercise,
            'folder' => $folderData,
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

    public function folders(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
        $data = $request->validate(['folder_id' => ['nullable', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/'],
            'cursor' => ['nullable', 'string', 'max:4096'], 'suggest' => ['sometimes', 'boolean']]);
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
        $data = $request->validate(['folder_id' => ['required', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/']]);
        $connection = $dropbox->connection($actor);
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
        $data = $request->validate(['apply' => ['sometimes', 'boolean'], 'hash' => ['required_if:apply,1', 'nullable', 'string', 'size:64']]);

        return $dropbox->withImportFiles($actor, $course_work, function (array $parameters, array $uploads) use ($request, $course_work, $data): JsonResponse {
            $importRequest = Request::createFrom($request);
            $importRequest->replace($parameters + $data);
            $importRequest->files->replace($uploads);
            $importRequest->server->set('CONTENT_LENGTH', 0);

            return app(CourseWorkController::class)->importJson($importRequest, $course_work, app(TeachingWorkJsonImport::class),
                app(TeachingWorkFolderImport::class), app(TeachingWorkDispatchImport::class));
        });
    }

    private function actor(TeachingCourseWork $work): User
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);

        return $actor;
    }
}
