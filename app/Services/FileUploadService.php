<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Intervention\Image\Image;
use Intervention\Image\ImageManager;

class FileUploadService
{
    // Called by POST /uploadLogo
    public function upload(): string
    {
        // 1) Create a unique upload id and temp dir
        $id = Str::uuid()->toString();
        $dir = storage_path("app/private/temp/{$id}");
        if (!is_dir($dir)) {
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
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $part = "{$dir}/file.part";

        // Append raw chunk body
        $bytes = $request->getContent();
        if ($bytes === '' || $bytes === null) {
            return response('NO_CONTENT', 204);
        }
        file_put_contents($part, $bytes, FILE_APPEND);

        // Detect completion
        $total = (int) ($request->header('Upload-Length') ?? 0);
        $size = filesize($part) ?: 0;

        if ($total > 0 && $size >= $total) {
            // Finalize
            $name = $request->header('Upload-Name') ?: 'upload.bin';
            $extension = pathinfo($name, PATHINFO_EXTENSION);

            if ($new_name) {
                $name = "{$new_name}" . ($extension ? ".{$extension}" : '');
            }

            $destDir = storage_path(trim($upload_path, '/'));
            if (!is_dir($destDir)) mkdir($destDir, 0775, true);

            $newPath = "{$destDir}/{$name}";
            rename($part, $newPath);

            if ($fit && is_array($fit)) {
                $manager = ImageManager::gd();
                $image = $manager->read($newPath);


                if (array_key_exists('width', $fit)  && array_key_exists('height', $fit)) {
                    $image = $image->scale($fit['width'],  $fit['height']);
                } elseif (array_key_exists('width', $fit)) {
                    $image = $image->scale(width: $fit['width']);
                } elseif (array_key_exists('height', $fit)) {
                    $image = $image->scale(height: $fit['height']);
                }
                $image->save();
            }

            // (Optional) image resizing AFTER upload, not during chunks

            return $name; // FilePond serverId / confirmation
        }

        // Not finished yet → ACK the chunk
        return response('OK', 200);
    }
}
