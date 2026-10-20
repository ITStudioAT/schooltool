<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

class ModuleHandshake
{
    public const MAX_BYTES = 4096;

    /** @return array{schema_version: int, module_id: string} */
    public function parse(string $contents): array
    {
        try {
            $data = strlen($contents) <= self::MAX_BYTES ? json_decode($contents, false, 8, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $data = null;
        }
        if (! $data instanceof stdClass || count(get_object_vars($data)) !== 2
            || ($data->schema_version ?? null) !== 1 || ! is_string($data->module_id ?? null)
            || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $data->module_id)) {
            throw ValidationException::withMessages(['handshake' => 'handshake.json muss genau schema_version: 1 und eine gültige module_id (UUID) enthalten.']);
        }
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s', $contents, $matches);
        $keys = array_map(fn (string $match): string => json_decode(rtrim(substr(rtrim($match), 0, -1)), flags: JSON_THROW_ON_ERROR), $matches[0]);
        if (count($keys) !== 2 || count(array_unique($keys)) !== 2) {
            throw ValidationException::withMessages(['handshake' => 'handshake.json enthält doppelte oder zusätzliche Felder.']);
        }

        return ['schema_version' => 1, 'module_id' => $data->module_id];
    }
}
