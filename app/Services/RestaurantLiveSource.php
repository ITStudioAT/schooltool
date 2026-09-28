<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class RestaurantLiveSource
{
    public function snapshot(School $school): array
    {
        $request = base64_encode(json_encode([
            'school' => ['id' => $school->id, 'short_name' => $school->short_name, 'long_name' => $school->long_name],
            'tables' => RestaurantSynchronisationService::TABLES,
            'users' => RestaurantSynchronisationService::USER_FIELDS,
            'imports' => RestaurantSynchronisationService::IMPORT_FIELDS,
        ], JSON_THROW_ON_ERROR));
        $process = new Process([
            'powershell.exe', '-NoProfile', '-NonInteractive', '-File',
            base_path('scripts/restaurant-live-read.ps1'), '-Request', $request,
        ], base_path(), self::processEnvironment(), null, 120);
        $process->run();
        if (! $process->isSuccessful() || strlen($process->getOutput()) > 64 * 1024 * 1024) {
            $errorOutput = str_replace("\0", '', $process->getErrorOutput());
            $classifications = ['execution_policy' => 'PSSecurityException|running scripts is disabled|ExecutionPolicy',
                'arguments' => 'ParameterBindingException|NamedParameterNotFound|PositionalParameterNotFound',
                'syntax' => 'ParserError', 'file' => 'Cannot find path|FileNotFoundException',
                'ssh_auth' => 'Permission denied|Load key|Host key verification failed'];
            $errorKinds = [];
            foreach ($classifications as $kind => $pattern) {
                if (preg_match('/'.$pattern.'/i', $errorOutput)) {
                    $errorKinds[] = $kind;
                }
            }
            Log::warning('Restaurant live reader process failed', ['exit_code' => $process->getExitCode(),
                'stderr_bytes' => strlen($process->getErrorOutput()), 'stdout_bytes' => strlen($process->getOutput()),
                'error_kinds' => $errorKinds]);
            $stages = ['bootstrap' => 'Initialisierung', 'read_only_connection' => 'geschützte Leseverbindung',
                'schema' => 'Datenbankschema', 'school_identity' => 'Schulidentität', 'restaurant_rows' => 'Restaurantdaten',
                'sepa_decryption' => 'SEPA-Entschlüsselung', 'shared_identity' => 'Benutzer- und Schülerzuordnungen',
                'image_storage_roots' => 'Bildverzeichnisse', 'image_path' => 'Bildpfade', 'image_missing' => 'fehlende Bilddateien',
                'image_safety' => 'Bildsicherheit oder Dateigröße', 'image_contents' => 'Bilddateien', 'encoding' => 'Übertragungsgröße'];
            if (preg_match('/Restaurant read-only export failed at ([a-z_]+);/', $errorOutput, $match) && isset($stages[$match[1]])) {
                throw new RuntimeException('Der Live-Lesezugriff wurde im Schritt „'.$stages[$match[1]].'“ blockiert. Es wurden keine Daten übernommen.');
            }
            $localStages = ['configuration' => 'lokale SSH-Konfiguration', 'program' => 'lokales Exportprogramm', 'ssh' => 'SSH-Verbindung'];
            if (preg_match('/Restaurant local read failed at ([a-z_]+)\./', $errorOutput, $match) && isset($localStages[$match[1]])) {
                throw new RuntimeException('Der Live-Lesezugriff wurde im Schritt „'.$localStages[$match[1]].'“ blockiert. Es wurden keine Daten übernommen.');
            }
            throw new RuntimeException('Der geschützte Live-Lesezugriff ist fehlgeschlagen. SSH-Konfiguration, Schema und Dateizugriff prüfen; es wurden keine Daten übernommen.');
        }

        try {
            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Live hat keinen vollständigen Restaurant-Snapshot geliefert.');
        }
    }

    /** @return array<string, string> */
    private static function processEnvironment(): array
    {
        // Laravel/Symfony's web environment filtering must not remove Windows runtime variables.
        // Read the native process environment, never request headers or server parameters.
        $environment = [];
        $allowed = array_map('strtolower', ['SystemRoot', 'WINDIR', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'ProgramFiles', 'ProgramFiles(x86)',
            'ProgramData', 'ALLUSERSPROFILE', 'HOME', 'HOMEDRIVE', 'HOMEPATH', 'SystemDrive',
            'PATH', 'PATHEXT', 'COMSPEC', 'PSModulePath', 'TEMP', 'TMP', 'OS', 'SSH_AUTH_SOCK',
            'SCHOOLTOOL_MAIN_SSH', 'SCHOOLTOOL_MAIN_PATH', 'SCHOOLTOOL_MAIN_KEY',
            'SCHOOLTOOL_MAIN_UNIX_USER', 'SCHOOLTOOL_MAIN_KNOWN_HOSTS']);
        foreach (getenv(null, true) as $key => $value) {
            if (in_array(strtolower($key), $allowed, true) && is_string($value) && $value !== '') {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }
}
