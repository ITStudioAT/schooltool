<?php

namespace App\Services\Teaching;

use App\Models\TeachingCurriculum;
use App\Models\TeachingCurriculumDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CurriculumUnitFileService
{
    /**
     * @param  iterable<int, TeachingCurriculum>  $curricula
     * @return array<int, array<string, array<string, int>>>
     */
    public function countsByCurriculum(iterable $curricula): array
    {
        $curriculumIds = collect($curricula)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($curriculumIds->isEmpty()) {
            return [];
        }

        $counts = [];
        $rows = TeachingCurriculumDocument::query()
            ->select(['teaching_curriculum_id', 'topic_id', 'unit_id'])
            ->selectRaw('COUNT(*) AS file_count')
            ->whereIn('teaching_curriculum_id', $curriculumIds)
            ->where('source_type', 'unit_file')
            ->groupBy(['teaching_curriculum_id', 'topic_id', 'unit_id'])
            ->get();

        foreach ($rows as $row) {
            $curriculumId = (int) $row->teaching_curriculum_id;
            $topicId = (string) $row->topic_id;
            $unitId = (string) $row->unit_id;

            if ($curriculumId <= 0 || $topicId === '' || $unitId === '') {
                continue;
            }

            $counts[$curriculumId][$topicId][$unitId] = (int) $row->file_count;
        }

        return $counts;
    }

    /**
     * @param  null|array<string, array<string, int>>  $counts
     * @return array<string, mixed>
     */
    public function curriculumPayload(TeachingCurriculum $curriculum, ?array $counts = null): array
    {
        $payload = $curriculum->toArray();
        $allCounts = $counts === null ? $this->countsByCurriculum([$curriculum]) : [];
        $payload['unit_file_counts'] = $counts ?? ($allCounts[$curriculum->id] ?? []);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function curriculumDetailPayload(TeachingCurriculum $curriculum): array
    {
        $files = $curriculum->documents()
            ->where('source_type', 'unit_file')
            ->latest('id')
            ->get();
        $unitFiles = [];
        $counts = [];

        foreach ($files as $file) {
            $topicId = (string) $file->topic_id;
            $unitId = (string) $file->unit_id;

            if (! $this->unitExists($curriculum, $topicId, $unitId)) {
                continue;
            }

            $unitFiles[$topicId][$unitId][] = $this->payload($curriculum, $topicId, $unitId, $file);
            $counts[$topicId][$unitId] = ($counts[$topicId][$unitId] ?? 0) + 1;
        }

        return $this->curriculumPayload($curriculum, $counts) + ['unit_files' => $unitFiles];
    }

    public function unitExists(TeachingCurriculum $curriculum, string $topicId, string $unitId): bool
    {
        return collect($curriculum->topics)
            ->contains(function (array $topic) use ($topicId, $unitId): bool {
                if ((string) ($topic['id'] ?? '') !== $topicId) {
                    return false;
                }

                return collect($topic['units'] ?? [])
                    ->contains(fn (array $unit): bool => (string) ($unit['id'] ?? '') === $unitId);
            });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, TeachingCurriculumDocument>
     */
    public function store(
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        array $files
    ): Collection {
        $diskName = trim((string) config('filesystems.default')) ?: 'local';
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($curriculum, $topicId, $unitId, $files, $diskName, &$storedPaths): Collection {
                return collect($files)->map(function (UploadedFile $file) use ($curriculum, $topicId, $unitId, $diskName, &$storedPaths): TeachingCurriculumDocument {
                    $extension = trim((string) $file->guessExtension());
                    $storedName = (string) Str::uuid().($extension !== '' ? ".{$extension}" : '');
                    $directory = "teaching/curriculum_unit_files/{$curriculum->id}";
                    $storedPath = $file->storeAs($directory, $storedName, $diskName);

                    if (! is_string($storedPath) || $storedPath === '') {
                        throw ValidationException::withMessages([
                            'files' => 'Die Datei konnte nicht gespeichert werden.',
                        ]);
                    }

                    $storedPaths[] = $storedPath;

                    return $curriculum->documents()->create([
                        'topic_id' => $topicId,
                        'unit_id' => $unitId,
                        'source_type' => 'unit_file',
                        'name' => $this->displayName($file),
                        'file_path' => $storedPath,
                        'storage_disk' => $diskName,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize() ?: null,
                    ]);
                });
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk($diskName)->delete($storedPaths);
            }

            throw $exception;
        }
    }

    public function delete(TeachingCurriculumDocument $document): void
    {
        if ($document->file_path) {
            $disk = Storage::disk($this->diskName($document));

            if ($disk->exists($document->file_path) && ! $disk->delete($document->file_path)) {
                throw ValidationException::withMessages([
                    'file' => 'Die Datei konnte nicht gelöscht werden.',
                ]);
            }
        }

        $document->delete();
    }

    public function rename(TeachingCurriculumDocument $document, string $basename): TeachingCurriculumDocument
    {
        $name = $basename.$this->filenameExtension((string) $document->name);

        if (mb_strlen($name) > 255) {
            throw ValidationException::withMessages([
                'basename' => 'Der Dateiname darf einschließlich Dateiendung höchstens 255 Zeichen lang sein.',
            ]);
        }

        $document->update(['name' => $name]);

        return $document->refresh();
    }

    public function deleteFilesForMissingUnits(TeachingCurriculum $curriculum): void
    {
        $validScopes = collect($curriculum->topics)
            ->flatMap(fn (array $topic): array => collect($topic['units'] ?? [])
                ->map(fn (array $unit): string => $this->scopeKey(
                    (string) ($topic['id'] ?? ''),
                    (string) ($unit['id'] ?? '')
                ))
                ->all())
            ->flip();

        $curriculum->documents()
            ->where('source_type', 'unit_file')
            ->get()
            ->reject(fn (TeachingCurriculumDocument $document): bool => $validScopes->has(
                $this->scopeKey((string) $document->topic_id, (string) $document->unit_id)
            ))
            ->each(fn (TeachingCurriculumDocument $document) => $this->delete($document));
    }

    public function deleteAll(TeachingCurriculum $curriculum): void
    {
        $curriculum->documents()
            ->where('source_type', 'unit_file')
            ->get()
            ->each(fn (TeachingCurriculumDocument $document) => $this->delete($document));
    }

    public function diskName(TeachingCurriculumDocument $document): string
    {
        $diskName = trim((string) $document->storage_disk) ?: 'local';

        return $diskName === 's3' && config('schooltool.preview.instance', false) ? 'local' : $diskName;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(
        TeachingCurriculum $curriculum,
        string $topicId,
        string $unitId,
        TeachingCurriculumDocument $document
    ): array {
        $baseUrl = "/api/admin/teaching/curricula/{$curriculum->id}/topics/"
            .rawurlencode($topicId).'/units/'.rawurlencode($unitId)."/files/{$document->id}";

        return [
            'id' => (int) $document->id,
            'name' => (string) $document->name,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes !== null ? (int) $document->size_bytes : null,
            'preview_url' => "{$baseUrl}/preview",
            'download_url' => "{$baseUrl}/download",
            'created_at' => $document->created_at?->toDateTimeString(),
        ];
    }

    private function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'Datei';

        return Str::limit(trim($name), 255, '');
    }

    private function filenameExtension(string $name): string
    {
        $lastDotPosition = strrpos($name, '.');

        if ($lastDotPosition === false || $lastDotPosition === 0 || $lastDotPosition === strlen($name) - 1) {
            return '';
        }

        return substr($name, $lastDotPosition);
    }

    private function scopeKey(string $topicId, string $unitId): string
    {
        return "{$topicId}\0{$unitId}";
    }
}
