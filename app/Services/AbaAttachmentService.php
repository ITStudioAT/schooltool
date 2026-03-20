<?php

namespace App\Services;

use App\Models\Aba;
use App\Models\AbaAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AbaAttachmentService
{
    private const DEFAULT_MAX_UPLOAD_SIZE_KB = 30720; // 30 MB

    public function addAttachmentFromTempUpload(
        User $user,
        Aba $aba,
        string $uploadId,
        string $documentKind,
        ?string $originalName = null,
    ): AbaAttachment {
        $normalizedUploadId = $this->normalizeUploadId($uploadId);
        $normalizedKind = $this->normalizeDocumentKind($documentKind);

        $tempPath = $this->tempUploadPathById($user, $normalizedUploadId);
        if ($tempPath === null || ! Storage::disk('local')->exists($tempPath)) {
            throw ValidationException::withMessages([
                'data.upload_id' => 'Upload wurde nicht gefunden.',
            ]);
        }

        $sizeBytes = (int) (Storage::disk('local')->size($tempPath) ?: 0);
        $maxBytes = $this->maxUploadSizeKbForUser($user) * 1024;
        if ($maxBytes > 0 && $sizeBytes > $maxBytes) {
            Storage::disk('local')->delete($tempPath);
            throw ValidationException::withMessages([
                'data.upload_id' => 'Datei überschreitet die maximal erlaubte Uploadgröße.',
            ]);
        }

        $tempFileName = basename($tempPath);
        $displayName = $this->normalizeDisplayName($originalName, $tempFileName);
        $storedName = $this->storedFileNameFromOriginalName($displayName);
        $destinationPath = $this->attachmentDirectory($aba).'/'.$storedName;

        $stream = Storage::disk('local')->readStream($tempPath);
        $moved = $stream !== false && Storage::disk('local')->put($destinationPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        Storage::disk('local')->delete($tempPath);

        if (! $moved) {
            throw ValidationException::withMessages([
                'data.upload_id' => 'Upload konnte nicht übernommen werden.',
            ]);
        }

        $mimeType = Storage::disk('local')->mimeType($destinationPath);
        $normalizedMimeType = $this->normalizeMimeType((string) ($mimeType ?? ''));
        $finalSize = (int) (Storage::disk('local')->size($destinationPath) ?: $sizeBytes);

        if ($normalizedKind === AbaAttachment::DOCUMENT_KIND_MAIN) {
            $this->replaceMainDocument($aba);
        }

        return $aba->attachments()->create([
            'document_kind' => $normalizedKind,
            'original_name' => $displayName,
            'path' => $destinationPath,
            'stored_name' => $storedName,
            'disk' => 'local',
            'mime_type' => $normalizedMimeType !== '' ? $normalizedMimeType : null,
            'size_bytes' => $finalSize > 0 ? $finalSize : null,
            'uploaded_by_user_id' => $user->id,
        ]);
    }

    public function deleteTempUpload(User $user, string $uploadId): void
    {
        $normalizedUploadId = $this->normalizeUploadId($uploadId);
        $tempPath = $this->tempUploadPathById($user, $normalizedUploadId);
        if ($tempPath === null) {
            return;
        }

        Storage::disk('local')->delete($tempPath);
    }

    public function deleteAttachment(AbaAttachment $attachment): void
    {
        $disk = trim((string) ($attachment->disk ?? ''));
        $path = trim((string) ($attachment->path ?? ''));

        if ($path !== '') {
            $targetDisk = $disk !== '' ? $disk : 'local';
            if (Storage::disk($targetDisk)->exists($path)) {
                Storage::disk($targetDisk)->delete($path);
            }
        }

        $attachment->delete();
    }

    public function resolveTempUploadPathForUser(User $user, string $uploadId): ?string
    {
        return $this->tempUploadPathById($user, $uploadId);
    }

    public function tempUploadStoragePathForUser(User $user): string
    {
        return 'app/private/'.$this->tempUploadDirectoryForUser($user);
    }

    public function maxUploadSizeKbForUser(User $user): int
    {
        return (int) config('schooltool.aba_max_upload_size_kb', self::DEFAULT_MAX_UPLOAD_SIZE_KB);
    }

    private function replaceMainDocument(Aba $aba): void
    {
        $existingMainDocuments = $aba->attachments()
            ->where('document_kind', AbaAttachment::DOCUMENT_KIND_MAIN)
            ->get();

        foreach ($existingMainDocuments as $attachment) {
            $path = trim((string) ($attachment->path ?? ''));
            if ($path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            $attachment->delete();
        }
    }

    private function attachmentDirectory(Aba $aba): string
    {
        $now = now();

        return implode('/', [
            'aba',
            'schools',
            (string) $aba->school_id,
            'users',
            (string) $aba->user_id,
            'entries',
            (string) $aba->id,
            'attachments',
            $now->format('Y'),
            $now->format('m'),
        ]);
    }

    private function storedFileNameFromOriginalName(string $originalName): string
    {
        $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug($baseName, '-');
        if ($slug === '') {
            $slug = 'dokument';
        }

        $slug = mb_substr($slug, 0, 120);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $suffix = $extension !== '' ? '.'.$extension : '';

        return Str::uuid()->toString().'-'.$slug.$suffix;
    }

    private function normalizeDisplayName(?string $name, string $fallbackFileName): string
    {
        $value = trim((string) $name);
        if ($value !== '') {
            return mb_substr($value, 0, 255);
        }

        $clean = preg_replace('/^[a-f0-9-]{20,64}-?/i', '', (string) pathinfo($fallbackFileName, PATHINFO_FILENAME));
        $clean = trim((string) $clean);
        if ($clean === '') {
            $clean = pathinfo($fallbackFileName, PATHINFO_FILENAME) ?: 'dokument';
        }

        $extension = strtolower((string) pathinfo($fallbackFileName, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return mb_substr($clean, 0, 240).'.'.$extension;
        }

        return mb_substr($clean, 0, 255);
    }

    private function normalizeDocumentKind(string $documentKind): string
    {
        $value = trim($documentKind);
        $allowed = [
            AbaAttachment::DOCUMENT_KIND_MAIN,
            AbaAttachment::DOCUMENT_KIND_ADDITIONAL,
        ];

        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([
                'data.document_kind' => 'Ungültiger Dokumenttyp.',
            ]);
        }

        return $value;
    }

    private function normalizeUploadId(string $uploadId): string
    {
        $value = trim($uploadId);
        if ($value === '' || ! preg_match('/^[a-f0-9-]{20,64}$/i', $value)) {
            throw ValidationException::withMessages([
                'data.upload_id' => 'Ungültige Upload-ID.',
            ]);
        }

        return $value;
    }

    private function tempUploadDirectoryForUser(User $user): string
    {
        return implode('/', [
            'aba',
            'temp',
            (string) $user->school_id,
            (string) $user->id,
        ]);
    }

    private function tempUploadPathById(User $user, string $uploadId): ?string
    {
        $normalizedUploadId = $this->normalizeUploadId($uploadId);
        $directory = $this->tempUploadDirectoryForUser($user);
        $files = Storage::disk('local')->files($directory);

        foreach ($files as $file) {
            $name = basename((string) $file);
            if (Str::startsWith($name, $normalizedUploadId)) {
                return $file;
            }
        }

        return null;
    }

    private function normalizeMimeType(?string $mimeType): ?string
    {
        $raw = trim((string) $mimeType);
        if ($raw === '') {
            return null;
        }

        $normalized = strtolower(trim(explode(';', $raw)[0] ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
