<?php

namespace App\Services;

use App\Mail\PersonalTeachingBackupMail;
use App\Models\PersonalTeachingBackup;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PersonalTeachingBackupDeliveryService
{
    public const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    public function __construct(private PersonalTeachingBackupFileService $files) {}

    public function deliver(User $user, PersonalTeachingBackup $backup): void
    {
        abort_unless((int) $backup->user_id === (int) $user->id && (int) $backup->school_id === (int) $user->school_id, 403);

        if ($backup->mail_status === 'sent') {
            return;
        }

        $email = trim((string) $user->email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || str_ends_with(strtolower($email), '.noemail')) {
            $this->setStatus($backup, 'failed', 'Keine gültige E-Mail-Adresse hinterlegt. Die Sicherung wurde erstellt und kann heruntergeladen werden.');

            return;
        }

        if (! $this->canDeliverMail((string) config('mail.default'))) {
            $this->setStatus($backup, 'failed', 'Der E-Mail-Versand ist nicht eingerichtet. Die Sicherung wurde erstellt und kann heruntergeladen werden.');

            return;
        }

        try {
            $contents = $this->files->contents($backup);
            if (strlen($contents) > self::MAX_ATTACHMENT_BYTES) {
                $this->setStatus($backup, 'too_large', 'Die Sicherung überschreitet die Anhangsgrenze von 10 MiB. Sie wurde erstellt; bitte laden Sie sie herunter.');

                return;
            }

            Mail::to($email)->send(new PersonalTeachingBackupMail(
                $contents,
                $this->files->filename($backup),
                $backup->created_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i'),
            ));
        } catch (Throwable $exception) {
            Log::warning('personal_teaching_backup.mail_failed', [
                'backup_id' => $backup->id,
                'exception_type' => $exception::class,
            ]);
            $this->setStatus($backup, 'failed', 'Die Sicherung wurde erstellt, aber die E-Mail konnte nicht versendet werden. Bitte laden Sie die Sicherung herunter.');

            return;
        }

        $this->setStatus($backup, 'sent', 'Die Sicherung wurde an Ihre hinterlegte E-Mail-Adresse versendet.');
    }

    private function setStatus(PersonalTeachingBackup $backup, string $status, string $message): void
    {
        $backup->forceFill([
            'mail_status' => $status,
            'mail_message' => $message,
            'mailed_at' => $status === 'sent' ? now() : null,
        ])->save();
    }

    /** @param array<int, string> $visited */
    private function canDeliverMail(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return false;
        }

        $mailer = config("mail.mailers.{$name}", []);
        $transport = $mailer['transport'] ?? null;
        if (! is_string($transport) || in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (! in_array($transport, ['failover', 'roundrobin'], true)) {
            return true;
        }

        $children = $mailer['mailers'] ?? [];
        if (! is_array($children) || $children === []) {
            return false;
        }

        foreach ($children as $child) {
            if (! is_string($child) || ! $this->canDeliverMail($child, [...$visited, $name])) {
                return false;
            }
        }

        return true;
    }
}
