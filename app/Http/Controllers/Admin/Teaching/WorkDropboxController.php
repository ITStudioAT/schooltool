<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
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

        return response()->json(['configured' => $configured, 'installed' => $installed, 'connected' => (bool) $connected,
            'folder' => $folder ? ['id' => $folder->folder_id, 'name' => $folder->folder_name] : null,
            'can_quick_import' => (bool) ($connected && $folder),
            'message' => ! $configured || ! $installed ? 'Dropbox ist noch nicht eingerichtet. Bitte Importieren verwenden.'
                : (! $connected ? 'Dropbox verbinden, um Quick-Import zu verwenden.'
                    : (! $folder ? 'Für diese Arbeit einen Dropbox-Ordner zuordnen.' : 'Dropbox-Ordner erneut lesen und Vorschau öffnen.'))]);
    }

    public function connect(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
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
        $work = TeachingCourseWork::query()->findOrFail($pending['work_id']);
        $actor = $this->actor($work);
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
            'cursor' => ['nullable', 'string', 'max:4096']]);

        return response()->json($dropbox->folders($dropbox->connection($actor), $data['folder_id'] ?? '', $data['cursor'] ?? null));
    }

    public function storeFolder(Request $request, TeachingCourseWork $course_work, DropboxWorkImport $dropbox): JsonResponse
    {
        $actor = $this->actor($course_work);
        $data = $request->validate(['folder_id' => ['required', 'string', 'max:255', 'regex:/\Aid:[A-Za-z0-9_-]+\z/']]);
        $connection = $dropbox->connection($actor);
        $folder = $dropbox->metadata($connection, $data['folder_id']);
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
