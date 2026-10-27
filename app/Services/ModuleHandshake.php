<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

class ModuleHandshake
{
    public const MAX_BYTES = 4096;

    /** @return array{schema_version: int, module_id: string, dropbox_folder_id?: string} */
    public function parse(string $contents): array
    {
        try {
            $data = strlen($contents) <= self::MAX_BYTES ? json_decode($contents, false, 8, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $data = null;
        }
        $version = $data instanceof stdClass ? ($data->schema_version ?? null) : null;
        $expectedKeys = match ($version) {
            1 => ['module_id', 'schema_version'],
            2 => ['dropbox_folder_id', 'module_id', 'schema_version'],
            default => [],
        };
        $objectKeys = $data instanceof stdClass ? array_keys(get_object_vars($data)) : [];
        sort($objectKeys);
        if ($expectedKeys === [] || $objectKeys !== $expectedKeys || ! is_string($data->module_id ?? null)
            || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $data->module_id)) {
            throw ValidationException::withMessages(['handshake' => 'handshake.json muss eine unterstützte Schemaversion und eine gültige module_id (UUID) enthalten.']);
        }
        if ($version === 2 && (! is_string($data->dropbox_folder_id ?? null)
            || ! preg_match('/\Aid:[A-Za-z0-9_-]+\z/', $data->dropbox_folder_id))) {
            throw ValidationException::withMessages(['handshake' => 'Die Dropbox-Ordnerkennung in handshake.json ist ungültig.']);
        }
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s', $contents, $matches);
        $keys = array_map(fn (string $match): string => json_decode(rtrim(substr(rtrim($match), 0, -1)), flags: JSON_THROW_ON_ERROR), $matches[0]);
        if (count($keys) !== count($expectedKeys) || count(array_unique($keys)) !== count($expectedKeys)) {
            throw ValidationException::withMessages(['handshake' => 'handshake.json enthält doppelte oder zusätzliche Felder.']);
        }

        return ['schema_version' => $version, 'module_id' => $data->module_id]
            + ($version === 2 ? ['dropbox_folder_id' => $data->dropbox_folder_id] : []);
    }
}
