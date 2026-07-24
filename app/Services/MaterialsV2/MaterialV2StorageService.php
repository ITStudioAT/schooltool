<?php

namespace App\Services\MaterialsV2;

use App\Models\MaterialV2Attachment;
use App\Models\MaterialV2Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MaterialV2StorageService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, MaterialV2Attachment>
     */
    public function store(MaterialV2Item $item, array $files): Collection
    {
        $diskName = (string) config('filesystems.default', 'local');
        $directory = "materials-v2/{$item->school_id}/{$item->user_id}/{$item->id}";
        $storedAttachments = collect();

        try {
            foreach ($files as $file) {
                $extension = Str::lower($file->getClientOriginalExtension());
                $storedName = (string) Str::uuid();
                if ($extension !== '') {
                    $storedName .= ".{$extension}";
                }

                $path = $file->storeAs($directory, $storedName, $diskName);
                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('Eine Anlage konnte nicht gespeichert werden.');
                }

                $storedAttachments->push($item->attachments()->create([
                    'disk' => $diskName,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                    'extraction_status' => MaterialV2Attachment::STATUS_PENDING,
                ]));
            }
        } catch (\Throwable $exception) {
            $this->deleteFiles($storedAttachments);

            throw $exception;
        }

        return $storedAttachments;
    }

    /**
     * @param  iterable<int, MaterialV2Attachment>  $attachments
     */
    public function deleteFiles(iterable $attachments): void
    {
        foreach ($attachments as $attachment) {
            $disk = trim((string) $attachment->disk);
            $path = trim((string) $attachment->path);

            if ($disk === '' || $path === '') {
                continue;
            }

            Storage::disk($disk)->delete($path);
        }
    }
}
