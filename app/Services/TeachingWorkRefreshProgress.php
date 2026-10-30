<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TeachingWorkRefreshProgress
{
    public function key(User $actor, TeachingCourseWork $work, string $id): string
    {
        return 'teaching-work-refresh-progress:'.hash('sha256', $actor->id.':'.$work->id.':'.$id);
    }

    /** @param array<string, mixed> $progress */
    public function save(?string $key, array $progress): void
    {
        if ($key !== null) {
            Cache::store('file')->put($key, $progress, now()->addMinutes(5));
        }
        $report = request()->attributes->get('teaching_work_progress_reporter');
        if ($report instanceof Closure) {
            $report('progress', $progress);
        }
    }

    public function stream(Request $request, Closure $operation): StreamedResponse
    {
        return response()->stream(function () use ($request, $operation): void {
            $previousIgnoreUserAbort = ignore_user_abort(true);
            $emit = function (string $type, array $data): void {
                echo json_encode(['type' => $type, 'data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
                if (! app()->runningUnitTests()) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            };
            $request->attributes->set('teaching_work_progress_reporter', $emit);
            try {
                $result = $operation();
                $emit('result', $result->getData(true));
            } catch (Throwable $exception) {
                $handler = app(ExceptionHandler::class);
                $handler->report($exception);
                $response = $handler->render($request, $exception);
                $body = $response instanceof JsonResponse ? $response->getData(true) : [];
                $emit('error', ['status' => $response->getStatusCode(), 'body' => is_array($body)
                    ? array_intersect_key($body, array_flip(['message', 'errors'])) : ['message' => 'Die Prüfung konnte nicht abgeschlossen werden.']]);
            } finally {
                $request->attributes->remove('teaching_work_progress_reporter');
                ignore_user_abort((bool) $previousIgnoreUserAbort);
            }
        }, 200, ['Content-Type' => 'application/x-ndjson', 'Cache-Control' => 'private, no-store', 'X-Accel-Buffering' => 'no']);
    }

    /** @return array<string, mixed>|null */
    public function read(string $key): ?array
    {
        $progress = Cache::store('file')->get($key);

        return is_array($progress) ? $progress : null;
    }
}
