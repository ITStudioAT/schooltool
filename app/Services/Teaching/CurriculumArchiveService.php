<?php

namespace App\Services\Teaching;

use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use App\Models\TeachingImportedCurriculum;
use App\Models\User;
use App\Services\Materials\MaterialService;
use App\Support\SafeExternalUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

class CurriculumArchiveService
{
    private const MAX_BYTES = 262144000;

    public function export(TeachingCurriculum $curriculum, User $user): string
    {
        $path = tempnam(storage_path('app/private'), 'curriculum_');
        $archive = new ZipArchive;
        $temporaryFiles = [];
        $totalBytes = 0;
        $isOpen = false;
        $materialKeys = [];

        try {
            if ($path === false || $archive->open($path, ZipArchive::OVERWRITE) !== true) {
                $this->invalid('Das Curriculum-Paket konnte nicht erstellt werden.');
            }
            $isOpen = true;

            $payload = app(CurriculumExportService::class)->transferPayload($curriculum);
            $payload['materials'] = [];
            $documents = $curriculum->documents()->with('materialCard.attachments')->get();
            $unitAssignments = [];
            foreach ($curriculum->topics ?? [] as $topic) {
                foreach ($topic['units'] ?? [] as $unit) {
                    foreach ($unit['materials'] ?? [] as $material) {
                        $unitAssignments[] = ['topic_id' => $topic['id'], 'unit_id' => $unit['id'], 'material_card_id' => $material['id']];
                        if (count($unitAssignments) + $documents->count() > 500) {
                            $this->invalid('Das Curriculum-Paket enthält zu viele Materialien.');
                        }
                    }
                }
            }
            $unitCards = MaterialCard::query()->with('attachments')->whereIn('id', array_column($unitAssignments, 'material_card_id'))->get()->keyBy('id');
            foreach ($unitAssignments as $assignment) {
                $card = $unitCards->get($assignment['material_card_id']);
                $document = new TeachingCurriculumDocument([
                    ...$assignment,
                    'source_type' => 'unit_material',
                    'name' => $card?->title ?? 'Material',
                ]);
                $document->setRelation('materialCard', $card);
                $documents->push($document);
            }

            foreach ($documents as $document) {
                $entry = $document->only(['source_type', 'name', 'topic_id', 'unit_id']);
                if (in_array($document->source_type, ['material', 'unit_material'], true)) {
                    $card = $document->materialCard;
                    if (! $card || ! app(MaterialService::class)->canUseMaterialForCurriculum($user, $card)) {
                        abort(403, 'Ein zugewiesenes Material ist nicht mehr freigegeben.');
                    }

                    $entry['material'] = $card->only(['title', 'source_text', 'source_url', 'subject', 'area', 'unit', 'type', 'notes', 'keywords']);
                    $entry['material']['key'] = $materialKeys[$card->id] ??= (string) Str::uuid();
                    $entry['material']['attachments'] = [];
                    foreach ($card->attachments as $attachment) {
                        $item = $attachment->only(['attachment_type', 'name', 'url', 'source_url', 'mime_type']);
                        $item['selected'] = (int) $document->material_card_attachment_id === (int) $attachment->id;
                        if ($attachment->attachment_type === MaterialCardAttachment::TYPE_FILE) {
                            $item['path'] = $this->addFile($archive, $this->materialStream((string) $attachment->file_path), $temporaryFiles, $totalBytes);
                        }
                        $entry['material']['attachments'][] = $item;
                    }
                } elseif (in_array($document->source_type, ['upload', 'unit_file'], true)) {
                    $stream = $document->source_type === 'upload' && ! $document->storage_disk
                        ? $this->uploadedStream((string) $document->file_path)
                        : Storage::disk(app(CurriculumUnitFileService::class)->diskName($document))->readStream((string) $document->file_path);
                    $entry['file'] = [
                        'name' => $document->name,
                        'mime_type' => $document->mime_type,
                        'path' => $this->addFile($archive, $stream, $temporaryFiles, $totalBytes),
                    ];
                } else {
                    $this->invalid('Ein Materialtyp kann nicht exportiert werden.');
                }

                $payload['materials'][] = $entry;
            }

            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > 2097152 || $totalBytes + strlen($json) > self::MAX_BYTES || count($payload['materials']) > 500 || count($temporaryFiles) >= 1000) {
                $this->invalid('Das Curriculum-Paket ist zu groß.');
            }
            $archive->addFromString('curriculum.json', $json);
            if (! $archive->close()) {
                $this->invalid('Das Curriculum-Paket konnte nicht erstellt werden.');
            }
            $isOpen = false;
            if (filesize($path) > 104857600) {
                $this->invalid('Das Curriculum-Paket überschreitet 100 MB.');
            }

            return $path;
        } catch (Throwable $exception) {
            if ($isOpen) {
                @$archive->close();
            }
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
            throw $exception;
        } finally {
            foreach ($temporaryFiles as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }
        }
    }

    public function import(User $user, UploadedFile $file): TeachingImportedCurriculum
    {
        $archive = $this->open((string) $file->getRealPath());
        try {
            $payload = $this->validatedPayload($archive);
        } finally {
            $archive->close();
        }
        $path = 'teaching/imported_curricula/'.Str::uuid().'.zip';
        $stream = fopen((string) $file->getRealPath(), 'rb');
        try {
            if (! is_resource($stream) || ! Storage::disk('local')->put($path, $stream)) {
                $this->invalid('Das Curriculum-Paket konnte nicht gespeichert werden.');
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        try {
            return DB::transaction(function () use ($user, $payload, $path): TeachingImportedCurriculum {
                $imported = app(ImportedCurriculumService::class)->importFromJson($user, json_encode($payload, JSON_THROW_ON_ERROR));
                $imported->forceFill(['materials' => ['archive_path' => $path]])->save();

                return $imported;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    /** @param array<int, string> $createdPaths */
    public function adopt(TeachingImportedCurriculum $imported, TeachingCurriculum $curriculum, User $user, array &$createdPaths): void
    {
        $path = $imported->materials['archive_path'] ?? null;
        if (! is_string($path)) {
            return;
        }
        if (! preg_match('~^teaching/imported_curricula/[a-f0-9-]{36}\.zip$~D', $path)) {
            $this->invalid();
        }

        $archive = $this->open(Storage::disk('local')->path($path));
        try {
            $payload = $this->validatedPayload($archive);
            $restoredMaterials = [];
            foreach ($payload['materials'] as $entry) {
                $attributes = collect($entry)->only(['source_type', 'name', 'topic_id', 'unit_id'])->all();
                if (in_array($entry['source_type'], ['material', 'unit_material'], true)) {
                    $key = $entry['material']['key'] ?? (string) Str::uuid();
                    $cardData = collect($entry['material'])->except(['attachments', 'key'])->all();
                    $existingMaterial = $restoredMaterials[$key] ?? null;
                    $card = $existingMaterial['card'] ?? MaterialCard::query()->create([
                        ...$cardData,
                        'school_id' => $user->school_id,
                        'user_id' => $user->id,
                        'status' => MaterialCard::STATUS_INBOX,
                    ]);
                    $attributes['material_card_id'] = $card->id;
                    $restoredAttachments = [];
                    foreach ($entry['material']['attachments'] as $attachmentIndex => $attachmentData) {
                        if ($existingMaterial !== null) {
                            $attachment = $existingMaterial['attachments'][$attachmentIndex];
                            if ($attachmentData['selected'] ?? false) {
                                $attributes['material_card_attachment_id'] = $attachment->id;
                            }

                            continue;
                        }
                        $attachmentAttributes = collect($attachmentData)->only(['attachment_type', 'name', 'url', 'source_url', 'mime_type'])->all();
                        if ($attachmentData['attachment_type'] === MaterialCardAttachment::TYPE_FILE) {
                            $storedPath = $this->storeFile($archive, $attachmentData['path'], $attachmentData['name'] ?? '', $createdPaths);
                            $attachmentAttributes['file_path'] = $storedPath;
                            $attachmentAttributes['size_bytes'] = Storage::disk('local')->size($storedPath);
                        }
                        $attachment = $card->attachments()->create($attachmentAttributes);
                        $restoredAttachments[] = $attachment;
                        if ($attachmentData['selected'] ?? false) {
                            $attributes['material_card_attachment_id'] = $attachment->id;
                        }
                    }
                    $restoredMaterials[$key] ??= ['card' => $card, 'attachments' => $restoredAttachments];
                } else {
                    $storedPath = $this->storeFile($archive, $entry['file']['path'], $entry['name'], $createdPaths);
                    $attributes['file_path'] = $storedPath;
                    $attributes['storage_disk'] = 'local';
                    $attributes['mime_type'] = $entry['file']['mime_type'] ?? 'application/octet-stream';
                    $attributes['size_bytes'] = Storage::disk('local')->size($storedPath);
                }
                if ($entry['source_type'] === 'unit_material') {
                    $topics = $curriculum->topics;
                    foreach ($topics as &$topic) {
                        if ($topic['id'] !== $entry['topic_id']) {
                            continue;
                        }
                        foreach ($topic['units'] as &$unit) {
                            if ($unit['id'] !== $entry['unit_id']) {
                                continue;
                            }
                            $unit['materials'][] = [
                                'id' => $card->id,
                                'title' => $card->title,
                                'subject' => $card->subject,
                                'topic' => $card->area,
                                'unit' => $card->unit,
                                'type' => $card->type,
                                'status' => $card->status,
                                'attachments_count' => count($entry['material']['attachments']),
                            ];
                        }
                        unset($unit);
                    }
                    unset($topic);
                    $curriculum->forceFill(['topics' => $topics])->save();
                } else {
                    $curriculum->documents()->create($attributes);
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function open(string $path): ZipArchive
    {
        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::RDONLY) !== true) {
            $this->invalid();
        }

        return $archive;
    }

    public function deleteStoredArchive(string $path): void
    {
        if (preg_match('~^teaching/imported_curricula/[a-f0-9-]{36}\.zip$~D', $path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /** @return array<string, mixed> */
    private function validatedPayload(ZipArchive $archive): array
    {
        if ($archive->numFiles > 1000) {
            $this->invalid();
        }
        $entries = [];
        $bytes = 0;
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);
            if (! is_array($stat)) {
                $this->invalid();
            }
            $name = $stat['name'];
            if (($name !== 'curriculum.json' && ! preg_match('~^files/[a-f0-9-]{36}$~D', $name)) || isset($entries[$name]) || ($stat['encryption_method'] ?? 0) !== 0) {
                $this->invalid();
            }
            $bytes += $stat['size'];
            if ($bytes > self::MAX_BYTES || ($name === 'curriculum.json' && $stat['size'] > 2097152)) {
                $this->invalid('Das Curriculum-Paket ist zu groß.');
            }
            $entries[$name] = true;
        }
        try {
            $payload = json_decode((string) $archive->getFromName('curriculum.json'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->invalid();
        }
        if (! is_array($payload)) {
            $this->invalid();
        }
        $normalized = app(ImportedCurriculumService::class)->normalizeImportPayload($payload);
        $payload['curriculum'] = $normalized['curriculum'];
        $topicIds = [];
        foreach ($payload['curriculum']['topics'] as $topic) {
            if (isset($topicIds[$topic['id']])) {
                $this->invalid();
            }
            $topicIds[$topic['id']] = true;
            $unitIds = [];
            foreach ($topic['units'] as $unit) {
                if (isset($unitIds[$unit['id']])) {
                    $this->invalid();
                }
                $unitIds[$unit['id']] = true;
            }
        }
        $validated = Validator::validate($payload, [
            'schema_version' => ['required', 'integer', 'in:1'],
            'materials' => ['present', 'array', 'max:500'],
            'materials.*' => ['array:source_type,name,topic_id,unit_id,file,material'],
            'materials.*.source_type' => ['required', 'in:upload,unit_file,material,unit_material'],
            'materials.*.name' => ['required', 'string', 'max:255'],
            'materials.*.topic_id' => ['nullable', 'string', 'max:100'],
            'materials.*.unit_id' => ['nullable', 'string', 'max:100'],
        ]);
        $references = ['curriculum.json' => true];
        $materialFingerprints = [];
        foreach ($validated['materials'] as $entry) {
            if (in_array($entry['source_type'], ['unit_file', 'unit_material'], true)) {
                $unitExists = collect($payload['curriculum']['topics'] ?? [])->contains(fn (array $topic): bool => ($topic['id'] ?? null) === ($entry['topic_id'] ?? null)
                    && collect($topic['units'] ?? [])->contains(fn (array $unit): bool => ($unit['id'] ?? null) === ($entry['unit_id'] ?? null)));
                if (! $unitExists || empty($entry['topic_id']) || empty($entry['unit_id'])) {
                    $this->invalid();
                }
            } elseif (($entry['topic_id'] ?? null) !== null || ($entry['unit_id'] ?? null) !== null) {
                $this->invalid();
            }
            if (in_array($entry['source_type'], ['material', 'unit_material'], true)) {
                $material = Validator::validate($entry, [
                    'material' => ['required', 'array:key,title,source_text,source_url,subject,area,unit,type,notes,keywords,attachments'],
                    'material.key' => ['nullable', 'uuid'],
                    'material.title' => ['required', 'string', 'max:255'],
                    'material.source_text' => ['nullable', 'string', 'max:1000000'],
                    'material.source_url' => ['nullable', 'string', 'max:8192'],
                    'material.subject' => ['nullable', 'string', 'max:255'],
                    'material.area' => ['nullable', 'string', 'max:255'],
                    'material.unit' => ['nullable', 'string', 'max:255'],
                    'material.type' => ['nullable', 'string', 'max:255'],
                    'material.notes' => ['nullable', 'string', 'max:60000'],
                    'material.keywords' => ['nullable', 'array', 'max:500'],
                    'material.keywords.*' => ['string', 'max:255'],
                    'material.attachments' => ['present', 'array', 'list', 'max:500'],
                    'material.attachments.*' => ['array:attachment_type,name,url,source_url,mime_type,path,selected'],
                    'material.attachments.*.attachment_type' => ['required', 'in:file,link'],
                    'material.attachments.*.name' => ['nullable', 'string', 'max:255'],
                    'material.attachments.*.url' => ['nullable', 'string', 'max:8192'],
                    'material.attachments.*.source_url' => ['nullable', 'string', 'max:8192'],
                    'material.attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
                    'material.attachments.*.selected' => ['sometimes', 'boolean'],
                ])['material'];
                $this->validateUrl($material['source_url'] ?? null);
                $selected = 0;
                foreach ($material['attachments'] as $attachment) {
                    $this->validateUrl($attachment['url'] ?? null);
                    $this->validateUrl($attachment['source_url'] ?? null);
                    if ($attachment['attachment_type'] === 'file') {
                        $this->reference($attachment['path'] ?? null, $entries, $references);
                    } elseif (empty($attachment['url']) || ! empty($attachment['selected'])) {
                        $this->invalid();
                    }
                    $selected += (int) ($attachment['selected'] ?? false);
                }
                if ($selected > 1) {
                    $this->invalid();
                }
                if (isset($material['key'])) {
                    $fingerprintMaterial = $material;
                    foreach ($fingerprintMaterial['attachments'] as &$attachment) {
                        unset($attachment['selected']);
                        if (isset($attachment['path'])) {
                            $stat = $archive->statName($attachment['path']);
                            unset($attachment['path']);
                            $attachment['file_identity'] = [$stat['size'], $stat['crc']];
                        }
                    }
                    unset($attachment);
                    $fingerprint = hash('sha256', json_encode($fingerprintMaterial, JSON_THROW_ON_ERROR));
                    if (isset($materialFingerprints[$material['key']]) && $materialFingerprints[$material['key']] !== $fingerprint) {
                        $this->invalid();
                    }
                    $materialFingerprints[$material['key']] = $fingerprint;
                }
            } else {
                Validator::validate($entry, [
                    'file' => ['required', 'array:name,mime_type,path'],
                    'file.name' => ['nullable', 'string', 'max:255'],
                    'file.mime_type' => ['nullable', 'string', 'max:255'],
                ]);
                $this->reference($entry['file']['path'] ?? null, $entries, $references);
            }
        }
        if (count($references) !== count($entries)) {
            $this->invalid();
        }

        foreach (array_keys($entries) as $member) {
            $stream = $archive->getStream($member);
            if (! is_resource($stream)) {
                $this->invalid();
            }
            try {
                $stat = $archive->statName($member);
                $hash = hash_init('crc32b');
                $read = hash_update_stream($hash, $stream, $stat['size'] + 1);
                if ($read !== $stat['size'] || strtolower(hash_final($hash)) !== sprintf('%08x', $stat['crc'])) {
                    $this->invalid('Das Curriculum-Paket enthält eine beschädigte Datei.');
                }
            } finally {
                fclose($stream);
            }
        }

        return $payload;
    }

    private function validateUrl(?string $url): void
    {
        if ($url !== null && $url !== '' && SafeExternalUrl::sanitize($url) === null) {
            $this->invalid('Das Paket enthält einen ungültigen Material-Link.');
        }
    }

    /** @param array<string, bool> $entries
     * @param  array<string, bool>  $references
     */
    private function reference(mixed $path, array $entries, array &$references): void
    {
        if (! is_string($path) || ! str_starts_with($path, 'files/') || ! isset($entries[$path]) || isset($references[$path])) {
            $this->invalid();
        }
        $references[$path] = true;
    }

    /** @param resource|false $stream
     * @param  array<int, string>  $temporaryFiles
     */
    private function addFile(ZipArchive $archive, mixed $stream, array &$temporaryFiles, int &$totalBytes): string
    {
        if (! is_resource($stream)) {
            $this->invalid('Eine zugewiesene Datei fehlt oder kann nicht gelesen werden.');
        }
        if (count($temporaryFiles) >= 999) {
            fclose($stream);
            $this->invalid('Das Curriculum-Paket enthält zu viele Dateien.');
        }
        $temporary = tempnam(storage_path('app/private'), 'curriculum_file_');
        if ($temporary === false) {
            fclose($stream);
            $this->invalid();
        }
        $temporaryFiles[] = $temporary;
        $output = fopen($temporary, 'wb');
        if (! is_resource($output)) {
            fclose($stream);
            $this->invalid();
        }
        try {
            $size = stream_copy_to_stream($stream, $output, self::MAX_BYTES - $totalBytes + 1);
            $totalBytes += $size ?: 0;
            if ($size === false || $totalBytes > self::MAX_BYTES) {
                $this->invalid('Das Curriculum-Paket ist zu groß.');
            }
        } finally {
            fclose($stream);
            fclose($output);
        }
        $member = 'files/'.Str::uuid();
        if (! $archive->addFile($temporary, $member)) {
            $this->invalid();
        }

        return $member;
    }

    /** @param array<int, string> $createdPaths */
    private function storeFile(ZipArchive $archive, string $member, string $name, array &$createdPaths): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,10}$/D', $extension) ? '.'.$extension : '';
        $path = 'teaching/curriculum_imports/'.Str::uuid().$extension;
        $stream = $archive->getStream($member);
        $createdPaths[] = $path;
        try {
            if (! is_resource($stream) || ! Storage::disk('local')->put($path, $stream)) {
                $this->invalid('Eine Materialdatei konnte nicht gespeichert werden.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    /** @return resource|false */
    private function uploadedStream(string $path): mixed
    {
        $fullPath = realpath(storage_path($path));
        $root = realpath(storage_path('app/private'));
        if ($fullPath === false || $root === false || ! str_starts_with(str_replace('\\', '/', $fullPath), str_replace('\\', '/', $root).'/')) {
            $this->invalid('Eine zugewiesene Datei fehlt oder kann nicht gelesen werden.');
        }

        return fopen($fullPath, 'rb');
    }

    /** @return resource|false */
    private function materialStream(string $path): mixed
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            $this->invalid();
        }
        foreach (array_unique([config('filesystems.default'), 'local', 'public']) as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                return $disk->readStream($path);
            }
        }
        foreach (['app/private', 'app', 'app/public'] as $directory) {
            $disk = Storage::build(['driver' => 'local', 'root' => storage_path($directory)]);
            if ($disk->exists($path)) {
                return $disk->readStream($path);
            }
        }
        $this->invalid('Eine zugewiesene Materialdatei fehlt.');
    }

    private function invalid(string $message = 'Das Curriculum-Paket ist ungültig oder unvollständig.'): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
