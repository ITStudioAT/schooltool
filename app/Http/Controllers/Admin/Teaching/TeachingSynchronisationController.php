<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TeachingSynchronisationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingSynchronisationController extends Controller
{
    public function status(): JsonResponse
    {
        $actor = $this->userHasRole(['super_admin']);

        return response()->json(['available' => $actor && TeachingSynchronisationService::available()], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function preview(TeachingSynchronisationService $service): JsonResponse
    {
        $actor = $this->actor();

        return $this->respond(fn (): array => ['data' => $service->preview($actor)]);
    }

    public function apply(Request $request, TeachingSynchronisationService $service): JsonResponse
    {
        $actor = $this->actor();
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/\A[a-zA-Z0-9]+\z/'],
            'replace_confirmed' => ['required', 'accepted'],
            'contacts_confirmed' => ['required', 'accepted'],
        ]);

        return $this->respond(fn (): array => $service->apply($actor, $validated['token']));
    }

    public function downloadBackup(string $backup, TeachingSynchronisationService $service): StreamedResponse
    {
        $actor = $this->actor();
        $path = $service->backupPath($actor, $backup);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $metadata = json_decode(Crypt::decryptString($disk->get($path)), true, 512, JSON_THROW_ON_ERROR);
        abort_unless($metadata['school'] === $actor->school_id && $metadata['actor'] === $actor->id, 403);

        return response()->streamDownload(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Sicherheitsbackup nicht lesbar.');
            }
            try {
                while (! feof($stream)) {
                    echo fread($stream, 1024 * 1024);
                }
            } finally {
                fclose($stream);
            }
        }, 'unterricht-sync-sicherheitsbackup.enc', ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store, private']);
    }

    private function actor(): User
    {
        $actor = $this->userHasRole(['super_admin']);
        abort_unless($actor, 403, 'Nur Superadmins dürfen Unterrichtsdaten synchronisieren.');
        abort_unless(TeachingSynchronisationService::available(), 403, 'Die Synchronisation ist nur lokal verfügbar.');

        return $actor;
    }

    private function respond(callable $operation): JsonResponse
    {
        try {
            return response()->json($operation(), 200, ['Cache-Control' => 'no-store, private']);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'Die Übernahme ist wegen Konflikten gesperrt.',
                'conflicts' => $exception->errors()['synchronisation'] ?? []], 422, ['Cache-Control' => 'no-store, private']);
        } catch (QueryException) {
            return response()->json(['message' => 'Schema oder Datenbankbeziehung passt nicht. Datenbankänderungen wurden zurückgerollt.'], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422, ['Cache-Control' => 'no-store, private']);
        }
    }
}
