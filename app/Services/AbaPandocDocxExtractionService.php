<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class AbaPandocDocxExtractionService
{
    /**
     * @return array{
     *   ok:bool,
     *   engine:string,
     *   format:string,
     *   input:array{path:string,extension:?string,size_bytes:?int},
     *   runtime:array{binary:?string,timeout_seconds:int,duration_ms:int,exit_code:?int},
     *   raw_json:?string,
     *   ast:?array<string,mixed>,
     *   metadata:array<string,mixed>,
     *   warnings:array<int,string>,
     *   error:?array{type:string,message:string,details:array<string,mixed>}
     * }
     */
    public function extractFromPath(string $docxPath): array
    {
        $normalizedPath = trim($docxPath);
        $timeoutSeconds = max(1, (int) config('aba_pandoc.timeout_seconds', 30));
        $base = $this->baseResult($normalizedPath, $timeoutSeconds);

        if (! (bool) config('aba_pandoc.enabled', true)) {
            return $this->withError(
                $base,
                'pandoc_disabled',
                'Pandoc-Extraktion ist deaktiviert.',
                []
            );
        }

        $preflightError = $this->validateInputPath($normalizedPath);
        if ($preflightError !== null) {
            return $this->withError($base, $preflightError['type'], $preflightError['message'], $preflightError['details']);
        }

        $binaryResolution = $this->resolveBinary();
        if (($binaryResolution['ok'] ?? false) !== true) {
            return $this->withError(
                $base,
                'binary_not_found',
                'Pandoc-Binary konnte nicht aufgelöst werden.',
                ['configured_binary' => $binaryResolution['configured'] ?? null]
            );
        }

        $binary = (string) ($binaryResolution['binary'] ?? '');
        $realPath = (string) (realpath($normalizedPath) ?: $normalizedPath);
        $extension = strtolower((string) pathinfo($realPath, PATHINFO_EXTENSION));
        $fileSize = @filesize($realPath);
        $sizeBytes = is_int($fileSize) ? $fileSize : null;

        $base['runtime']['binary'] = $binary;
        $base['input'] = [
            'path' => $realPath,
            'extension' => $extension !== '' ? $extension : null,
            'size_bytes' => $sizeBytes,
        ];

        $command = [
            $binary,
            '--from',
            'docx',
            '--to',
            'json',
            $realPath,
        ];

        $startedAt = microtime(true);
        try {
            $process = new Process($command);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $base['runtime']['duration_ms'] = $durationMs;
            $base['runtime']['exit_code'] = $process->getExitCode();

            if (! $process->isSuccessful()) {
                return $this->withError(
                    $base,
                    'process_failed',
                    'Pandoc-Aufruf ist fehlgeschlagen.',
                    [
                        'stderr' => trim($process->getErrorOutput()),
                        'stdout' => trim($process->getOutput()),
                    ]
                );
            }

            $rawJson = trim($process->getOutput());
            if ($rawJson === '') {
                return $this->withError(
                    $base,
                    'empty_output',
                    'Pandoc hat keine JSON-Ausgabe geliefert.',
                    []
                );
            }

            $decoded = json_decode($rawJson, true);
            if (! is_array($decoded)) {
                return $this->withError(
                    $base,
                    'invalid_json',
                    'Pandoc-Ausgabe ist kein gültiges JSON-AST.',
                    ['json_error' => json_last_error_msg()]
                );
            }

            $warnings = [];
            if (! isset($decoded['blocks']) || ! is_array($decoded['blocks'])) {
                $warnings[] = 'Pandoc-AST enthält kein erwartetes blocks-Array.';
            }

            $base['ok'] = true;
            $base['raw_json'] = $rawJson;
            $base['ast'] = $decoded;
            $base['warnings'] = $warnings;
            $base['metadata'] = [
                'pandoc_version' => $this->resolvePandocVersion($binary, $timeoutSeconds),
                'pandoc_api_version' => isset($decoded['pandoc-api-version']) && is_array($decoded['pandoc-api-version'])
                    ? array_values($decoded['pandoc-api-version'])
                    : null,
                'block_count' => is_array($decoded['blocks'] ?? null) ? count($decoded['blocks']) : 0,
                'meta_keys' => is_array($decoded['meta'] ?? null) ? array_keys($decoded['meta']) : [],
            ];

            return $base;
        } catch (\Throwable $exception) {
            $base['runtime']['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

            return $this->withError(
                $base,
                'process_exception',
                'Pandoc-Prozess konnte nicht ausgeführt werden.',
                ['exception' => $exception->getMessage()]
            );
        }
    }

    /**
     * @return array{type:string,message:string,details:array<string,mixed>}|null
     */
    private function validateInputPath(string $docxPath): ?array
    {
        if ($docxPath === '') {
            return [
                'type' => 'invalid_path',
                'message' => 'Es wurde kein Dateipfad übergeben.',
                'details' => [],
            ];
        }

        if (! is_file($docxPath)) {
            return [
                'type' => 'file_not_found',
                'message' => 'DOCX-Datei wurde nicht gefunden.',
                'details' => ['path' => $docxPath],
            ];
        }

        if (! is_readable($docxPath)) {
            return [
                'type' => 'file_not_readable',
                'message' => 'DOCX-Datei ist nicht lesbar.',
                'details' => ['path' => $docxPath],
            ];
        }

        $extension = strtolower((string) pathinfo($docxPath, PATHINFO_EXTENSION));
        if ($extension !== 'docx') {
            return [
                'type' => 'invalid_extension',
                'message' => 'Nur DOCX-Dateien werden unterstützt.',
                'details' => ['detected_extension' => $extension !== '' ? $extension : null],
            ];
        }

        return null;
    }

    /**
     * @return array{ok:bool,binary:?string,configured:?string}
     */
    private function resolveBinary(): array
    {
        $configured = trim((string) config('aba_pandoc.binary', ''));
        if ($configured === '') {
            return ['ok' => false, 'binary' => null, 'configured' => null];
        }

        if (
            str_contains($configured, DIRECTORY_SEPARATOR)
            || str_contains($configured, '/')
            || str_contains($configured, '\\')
            || preg_match('/^[A-Za-z]:\\\\/', $configured) === 1
        ) {
            $candidates = [];
            if ($this->isAbsolutePath($configured)) {
                $candidates[] = $configured;
            } else {
                $candidates[] = base_path($configured);
                $candidates[] = $configured;
            }

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return [
                        'ok' => true,
                        'binary' => $candidate,
                        'configured' => $configured,
                    ];
                }
            }

            return [
                'ok' => false,
                'binary' => null,
                'configured' => $configured,
            ];
        }

        $probe = PHP_OS_FAMILY === 'Windows'
            ? new Process(['where', $configured])
            : new Process(['which', $configured]);
        $probe->setTimeout(4);
        $probe->run();

        if (! $probe->isSuccessful()) {
            return ['ok' => false, 'binary' => null, 'configured' => $configured];
        }

        $lines = preg_split('/\R/', trim($probe->getOutput())) ?: [];
        $firstLine = trim((string) ($lines[0] ?? ''));

        return [
            'ok' => true,
            'binary' => $firstLine !== '' ? $firstLine : $configured,
            'configured' => $configured,
        ];
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return true;
        }

        return str_starts_with($path, '/') || str_starts_with($path, '\\');
    }

    private function resolvePandocVersion(string $binary, int $timeoutSeconds): ?string
    {
        try {
            $probe = new Process([$binary, '--version']);
            $probe->setTimeout(max(1, min(5, $timeoutSeconds)));
            $probe->run();

            if (! $probe->isSuccessful()) {
                return null;
            }

            $lines = preg_split('/\R/', trim($probe->getOutput())) ?: [];
            $firstLine = trim((string) ($lines[0] ?? ''));

            return str_starts_with(strtolower($firstLine), 'pandoc')
                ? $firstLine
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *   ok:bool,
     *   engine:string,
     *   format:string,
     *   input:array{path:string,extension:?string,size_bytes:?int},
     *   runtime:array{binary:?string,timeout_seconds:int,duration_ms:int,exit_code:?int},
     *   raw_json:?string,
     *   ast:?array<string,mixed>,
     *   metadata:array<string,mixed>,
     *   warnings:array<int,string>,
     *   error:?array{type:string,message:string,details:array<string,mixed>}
     * }
     */
    private function baseResult(string $docxPath, int $timeoutSeconds): array
    {
        return [
            'ok' => false,
            'engine' => 'pandoc',
            'format' => 'pandoc_json_ast',
            'input' => [
                'path' => $docxPath,
                'extension' => null,
                'size_bytes' => null,
            ],
            'runtime' => [
                'binary' => null,
                'timeout_seconds' => $timeoutSeconds,
                'duration_ms' => 0,
                'exit_code' => null,
            ],
            'raw_json' => null,
            'ast' => null,
            'metadata' => [],
            'warnings' => [],
            'error' => null,
        ];
    }

    /**
     * @param  array{
     *   ok:bool,
     *   engine:string,
     *   format:string,
     *   input:array{path:string,extension:?string,size_bytes:?int},
     *   runtime:array{binary:?string,timeout_seconds:int,duration_ms:int,exit_code:?int},
     *   raw_json:?string,
     *   ast:?array<string,mixed>,
     *   metadata:array<string,mixed>,
     *   warnings:array<int,string>,
     *   error:?array{type:string,message:string,details:array<string,mixed>}
     * }  $result
     * @param  array<string,mixed>  $details
     * @return array{
     *   ok:bool,
     *   engine:string,
     *   format:string,
     *   input:array{path:string,extension:?string,size_bytes:?int},
     *   runtime:array{binary:?string,timeout_seconds:int,duration_ms:int,exit_code:?int},
     *   raw_json:?string,
     *   ast:?array<string,mixed>,
     *   metadata:array<string,mixed>,
     *   warnings:array<int,string>,
     *   error:?array{type:string,message:string,details:array<string,mixed>}
     * }
     */
    private function withError(array $result, string $type, string $message, array $details): array
    {
        $result['error'] = [
            'type' => $type,
            'message' => $message,
            'details' => $details,
        ];

        return $result;
    }
}
