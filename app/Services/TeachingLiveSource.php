<?php

namespace App\Services;

use App\Models\School;
use RuntimeException;
use Symfony\Component\Process\Process;

class TeachingLiveSource
{
    public function snapshot(School $school): array
    {
        $request = base64_encode(json_encode(['school' => ['id' => $school->id,
            'short_name' => $school->short_name, 'long_name' => $school->long_name]], JSON_THROW_ON_ERROR));
        $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-File',
            base_path('scripts/teaching-live-read.ps1'), '-Request', $request], base_path(),
            RestaurantLiveSource::processEnvironment(), null, 600);
        $process->run();
        if (! $process->isSuccessful() || strlen($process->getOutput()) > 384 * 1024 * 1024) {
            $stages = ['bootstrap' => 'Initialisierung', 'read_only_connection' => 'geschützte Leseverbindung',
                'school_identity' => 'Schulidentität', 'schema' => 'Schema', 'teaching_rows' => 'Unterrichtsgraph',
                'files' => 'Dateien (fehlend, geändert, unsicher oder größer als 256 MiB)', 'encoding' => 'Übertragungsgröße',
                'configuration' => 'SSH-Konfiguration', 'program' => 'lokales Exportprogramm', 'ssh' => 'SSH-Verbindung'];
            preg_match('/Teaching (?:read-only export failed|local read failed) at ([a-z_]+)/', $process->getErrorOutput(), $match);
            $stage = $stages[$match[1] ?? ''] ?? 'Cloud-Lesezugriff';
            if (preg_match('/Teaching diagnostic: ([A-Za-z0-9+\/=]+)/', $process->getErrorOutput(), $diagnostic)) {
                $stage .= ' · '.base64_decode($diagnostic[1], true);
            }
            throw new RuntimeException("Der geschützte Live-Lesezugriff wurde blockiert: {$stage}. Es wurde nichts übernommen.");
        }

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
