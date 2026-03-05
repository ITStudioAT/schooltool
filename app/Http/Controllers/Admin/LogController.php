<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class LogController extends Controller
{
    public function listLogs(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $logFiles = glob(storage_path('logs/*.log')) ?: [];

        $logs = collect($logFiles)->map(function (string $path): array {
            return [
                'name' => basename($path),
                'size' => $this->formatBytes(filesize($path)),
                'modified' => date('d.m.Y H:i', filemtime($path)),
            ];
        })->sortByDesc('name')->values();

        return response()->json($logs);
    }

    public function getLog(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin', 'admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $filename = $request->input('filename');

        if ($filename) {
            $filename = basename($filename);
            $logPath = storage_path('logs/'.$filename);
        } else {
            $logPath = $this->resolveLogPath();
        }

        if (! $logPath || ! file_exists($logPath)) {
            return response()->json([
                'error' => 'Log-Datei nicht gefunden',
            ], 404);
        }

        $maxLines = $request->input('lines', 500);
        $maxLines = min($maxLines, 1000);
        $mode = $request->input('mode', 'first'); // 'last' oder 'first'

        $allLines = file($logPath);
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

    public function deleteLog(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $filename = $request->input('filename');

        if ($filename) {
            $filename = basename($filename);
            $logPath = storage_path('logs/'.$filename);
        } else {
            $logPath = $this->resolveLogPath();
        }

        if (! $logPath || ! file_exists($logPath)) {
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

    public function restartQueues(): \Illuminate\Http\Response
    {
        if (! $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        try {
            Artisan::call('cache:clear');
        } catch (\Throwable $exception) {
            Log::warning('cache:clear failed during queue restart.', [
                'message' => $exception->getMessage(),
            ]);
        }

        if (class_exists(\Laravel\Horizon\HorizonServiceProvider::class)) {
            try {
                Artisan::call('horizon:terminate');
            } catch (CommandNotFoundException) {
                // Horizon command is not available in this environment.
            } catch (\Throwable $exception) {
                Log::warning('horizon:terminate failed during queue restart.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        try {
            Artisan::call('queue:restart');
        } catch (\Throwable $exception) {
            Log::warning('queue:restart failed during queue restart.', [
                'message' => $exception->getMessage(),
            ]);
        }

        if (function_exists('exec')) {
            try {
                $healthCheckExitCode = Artisan::call('queue:health-check', [
                    '--restart' => true,
                ]);

                if ($healthCheckExitCode !== 0) {
                    Log::warning('Queue restart recovery command returned non-zero exit code.', [
                        'exit_code' => $healthCheckExitCode,
                    ]);
                }
            } catch (CommandNotFoundException) {
                // queue:health-check command is not available in this environment.
            } catch (\Throwable $exception) {
                Log::warning('queue:health-check failed during queue restart.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        } else {
            Log::info('queue:health-check skipped during queue restart because exec() is unavailable.');
        }

        return response()->noContent();
    }

    private function resolveLogPath(): ?string
    {
        $singleLogPath = storage_path('logs/laravel.log');

        if (file_exists($singleLogPath)) {
            return $singleLogPath;
        }

        $dailyLogs = glob(storage_path('logs/laravel-*.log')) ?: [];

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
