<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    public function listLogs(): JsonResponse
    {
        if (! $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $logFiles = glob(storage_path('logs/*.log')) ?: [];

        $logs = collect($logFiles)
            ->map(fn (string $path): ?string => $this->resolveContainedLogPath($path))
            ->filter()
            ->map(function (string $path): array {
                return [
                    'name' => basename($path),
                    'size' => $this->formatBytes((int) filesize($path)),
                    'modified' => date('d.m.Y H:i', (int) filemtime($path)),
                ];
            })
            ->sortByDesc('name')
            ->values();

        return response()->json($logs);
    }

    public function getLog(Request $request): JsonResponse|Response
    {
        if (! $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'filename' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\.log\z/'],
            'lines' => ['sometimes', 'integer', 'between:1,1000'],
            'mode' => ['sometimes', 'string', 'in:first,last'],
        ]);
        $logPath = $this->resolveRequestedLogPath($validated['filename'] ?? null);

        if (! $logPath) {
            return response()->json([
                'error' => 'Log-Datei nicht gefunden',
            ], 404);
        }

        $maxLines = $validated['lines'] ?? 500;
        $mode = $validated['mode'] ?? 'first';

        $allLines = file($logPath);

        if ($allLines === false) {
            return response()->json([
                'error' => 'Log-Datei konnte nicht gelesen werden',
            ], 500);
        }

        $totalLines = count($allLines);

        if ($mode === 'first') {
            $lines = array_slice($allLines, 0, $maxLines);
        } else {
            $lines = array_slice($allLines, -$maxLines);
        }

        $logContent = implode('', $lines);

        return response($logContent, 200)
            ->header('Content-Type', 'text/plain')
            ->header('X-Lines-Count', count($lines))
            ->header('X-Total-Lines', $totalLines)
            ->header('X-Mode', $mode);
    }

    public function deleteLog(Request $request): JsonResponse|Response
    {
        if (! $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'filename' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\.log\z/'],
        ]);
        $logPath = $this->resolveRequestedLogPath($validated['filename'] ?? null);

        if (! $logPath) {
            return response()->json([
                'error' => 'Log-Datei nicht gefunden',
            ], 404);
        }

        $backup = storage_path('logs/laravel_backup.log');

        // Kopiere zu Backup (überschreibt altes Backup)
        copy($logPath, $backup);

        // Leere die Original-Datei
        file_put_contents($logPath, '');

        return response()->noContent();
    }

    public function restartQueues(): Response
    {
        if (! $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        try {
            Artisan::call('queue:restart');
        } catch (\Throwable $exception) {
            Log::warning('queue:restart failed during queue restart.', [
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            Artisan::call('horizon:terminate');
        } catch (\Throwable $exception) {
            Log::warning('horizon:terminate failed during queue restart.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->noContent();
    }

    private function resolveRequestedLogPath(?string $filename): ?string
    {
        if ($filename === null) {
            return $this->resolveLogPath();
        }

        return $this->resolveContainedLogPath(storage_path("logs/{$filename}"));
    }

    private function resolveLogPath(): ?string
    {
        $singleLogPath = $this->resolveContainedLogPath(storage_path('logs/laravel.log'));

        if ($singleLogPath !== null) {
            return $singleLogPath;
        }

        $dailyLogs = collect(glob(storage_path('logs/laravel-*.log')) ?: [])
            ->map(fn (string $path): ?string => $this->resolveContainedLogPath($path))
            ->filter()
            ->values()
            ->all();

        if (empty($dailyLogs)) {
            return null;
        }

        usort($dailyLogs, static function (string $a, string $b): int {
            $mtimeCompare = filemtime($b) <=> filemtime($a);

            if ($mtimeCompare !== 0) {
                return $mtimeCompare;
            }

            return strcmp($b, $a);
        });

        return $dailyLogs[0] ?? null;
    }

    private function resolveContainedLogPath(string $path): ?string
    {
        $logDirectory = realpath(storage_path('logs'));
        $resolvedPath = realpath($path);

        if ($logDirectory === false || $resolvedPath === false) {
            return null;
        }

        if (! is_file($resolvedPath) || dirname($resolvedPath) !== $logDirectory) {
            return null;
        }

        return $resolvedPath;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
