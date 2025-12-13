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

        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            return response()->json([
                'error' => 'Log-Datei nicht gefunden'
            ], 404);
        }

        $logContent = file_get_contents($logPath);

        return response($logContent, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function deleteLog(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $logPath = storage_path('logs/laravel.log');
        $backup = storage_path('logs/laravel_backup.log');

        // Kopiere zu Backup (überschreibt altes Backup)
        copy($logPath, $backup);

        // Leere die Original-Datei
        file_put_contents($logPath, '');




        return response()->noContent();
    }
}
