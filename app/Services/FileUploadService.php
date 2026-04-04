<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class FileUploadService
{
    // Called by POST /uploadLogo
    public function upload(): string
    {
        // 1) Create a unique upload id and temp dir
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // 2) Some setups may send bytes on POST too (non-chunked)
        $firstBytes = request()->getContent();
        if ($firstBytes !== '' && $firstBytes !== null) {
            file_put_contents("{$dir}/file.part", $firstBytes, FILE_APPEND);
        }

        // 3) Return the server id as PLAIN TEXT (what FilePond expects)
        return $id;
    }

    // Called by PATCH /uploadLogo
    public function uploadNext(Request $request, string $upload_path, ?string $new_name = null, ?array $fit = null)
    {
        $id = $request->query('patch'); // FilePond sends ?patch=<serverId>
        abort_unless($id, 422, 'Missing upload id');

        $dir = storage_path("app/private/temp/{$id}");
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            abort(500, "Cannot create temp dir: {$dir}");
        }

        $part = "{$dir}/file.part";
        $metaOriginalNamePath = "{$dir}/upload_name.txt";

        $headerOriginalName = $request->header('Upload-Name');
        if (is_string($headerOriginalName) && trim($headerOriginalName) !== '') {
            @file_put_contents($metaOriginalNamePath, trim($headerOriginalName));
        }

        // Append raw chunk body
        $bytes = $request->getContent();
        if ($bytes === '' || $bytes === null) {
            return response('NO_CONTENT', 204);
        }

        if (file_put_contents($part, $bytes, FILE_APPEND) === false) {
            abort(500, "Failed writing chunk to: {$part}");
        }

        // Detect completion
        $total = (int) ($request->header('Upload-Length') ?? 0);
        $size = is_file($part) ? (filesize($part) ?: 0) : 0;

        if ($total > 0 && $size >= $total) {
            // Extension comes from original upload name header
            $originalName = $request->header('Upload-Name');
            if (! is_string($originalName) || trim($originalName) === '') {
                $originalName = is_file($metaOriginalNamePath) ? @file_get_contents($metaOriginalNamePath) : null;
            }
            $originalName = is_string($originalName) && trim($originalName) !== '' ? trim($originalName) : 'upload.bin';
            $request->attributes->set('upload_original_name', $originalName);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);

            // Filename base is provided by controller (e.g. logo_{schoolId})
            $base = $new_name ?: pathinfo($originalName, PATHINFO_FILENAME) ?: 'upload';
            $name = $base.($extension ? ".{$extension}" : '');

            // Destination directory: use the provided $upload_path under storage/
            $destDir = storage_path(trim($upload_path, '/'));
            if (! is_dir($destDir) && ! mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
                abort(500, "Cannot create dest dir: {$destDir}");
            }

            $newPath = "{$destDir}/{$name}";

            // Move finished file
            if (! @rename($part, $newPath)) {
                // fallback in case rename fails (e.g. cross-device)
                if (! @copy($part, $newPath) || ! @unlink($part)) {
                    $err = error_get_last();
                    abort(500, 'Failed to move uploaded file: '.($err['message'] ?? 'unknown error'));
                }
            }

            // Optional resize
            if ($fit && is_array($fit)) {
                $manager = ImageManager::gd();
                $image = $manager->read($newPath);

                if (isset($fit['width'], $fit['height'])) {
                    $image->scale($fit['width'], $fit['height']);
                } elseif (isset($fit['width'])) {
                    $image->scale(width: $fit['width']);
                } elseif (isset($fit['height'])) {
                    $image->scale(height: $fit['height']);
                }

                $image->save();
            }

            // Cleanup temp

            @unlink($part);
            @unlink($metaOriginalNamePath);
            @rmdir($dir);

            return $name; // FilePond confirmation / serverId
        }

        return response('OK', 200);
    }
}
