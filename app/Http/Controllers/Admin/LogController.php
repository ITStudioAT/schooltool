<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function getLog(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $logPath = $this->resolveLogPath();

        if (! $logPath || ! file_exists($logPath)) {
            return response()->json([
                'error' => 'Log-Datei nicht gefunden'
            ], 404);
        }

        $maxLines = $request->input('lines', 500);
        $maxLines = min($maxLines, 1000);
        $mode = $request->input('mode', 'first'); // 'last' oder 'first'

        $allLines = file($logPath);
        $totalLines = count($allLines);

        if ($mode === 'first') {
            // Erste X Zeilen
            $lines = array_slice($allLines, 0, $maxLines);
        } else {
            // Letzte X Zeilen (Standard)
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

        $logPath = $this->resolveLogPath();

        if (! $logPath || ! file_exists($logPath)) {
            return response()->json([
                'error' => 'Log-Datei nicht gefunden'
            ], 404);
        }

        $backup = storage_path('logs/laravel_backup.log');

        // Kopiere zu Backup (überschreibt altes Backup)
        copy($logPath, $backup);

        // Leere die Original-Datei
        file_put_contents($logPath, '');




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
}
