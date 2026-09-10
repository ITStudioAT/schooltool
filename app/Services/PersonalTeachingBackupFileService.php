<?php

namespace App\Services;

use App\Models\PersonalTeachingBackup;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class PersonalTeachingBackupFileService
{
    public function contents(PersonalTeachingBackup $backup): string
    {
        return Crypt::encryptString(json_encode([
            'format' => 'personal-teaching-backup',
            'created_at' => $backup->created_at->toISOString(),
            'payload' => $backup->payload,
            'summary' => $backup->summary,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function filename(PersonalTeachingBackup $backup): string
    {
        return "unterricht-sicherung-{$backup->id}.schooltool";
    }

    public function import(User $user, string $contents): PersonalTeachingBackup
    {
        try {
            $envelope = json_decode(Crypt::decryptString($contents), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['backup' => 'Die Sicherungsdatei ist beschädigt oder stammt nicht von dieser Schooltool-Installation.']);
        }

        $payload = $envelope['payload'] ?? null;
        if (! is_array($payload) || ($envelope['format'] ?? null) !== 'personal-teaching-backup'
            || ($payload['version'] ?? null) !== 1 || ! is_array($payload['tables'] ?? null)
            || ! is_array($payload['settings'] ?? null) || ! is_array($payload['files'] ?? null)) {
            throw ValidationException::withMessages(['backup' => 'Das Format dieser Sicherungsdatei wird nicht unterstützt.']);
        }

        abort_unless((int) ($payload['user_id'] ?? 0) === (int) $user->id
            && (int) ($payload['school_id'] ?? 0) === (int) $user->school_id, 403, 'Diese Sicherung gehört nicht zu Ihrem Benutzerkonto.');

        $backup = new PersonalTeachingBackup([
            'user_id' => $user->id,
            'school_id' => $user->school_id,
            'payload' => $payload,
            'summary' => $envelope['summary'] ?? [],
            'mail_status' => 'imported',
            'mail_message' => 'Sicherungsdatei importiert.',
        ]);
        $backup->created_at = $envelope['created_at'];
        $backup->save();

        return $backup;
    }
}
