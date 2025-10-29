<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;


class SchoolService
{

    public function create($data)
    {
        // Merken falls upload_file gesetzt ist
        $path = $data['upload_file'] ?? null;
        unset($data['upload_file']);


        // Schule anlegen
        $school  = School::create($data);

        // Logo verschieben
        if ($path) {
            $school = $this->moveLogo($school, $path);
        }

        return $school;
    }

    public function update($school, $data)
    {

        // Merken falls upload_file gesetzt ist
        $path = $data['upload_file'] ?? null;
        unset($data['upload_file']);

        // Schule updaten
        $school->update($data);

        // Logo verschieben
        if ($path) {
            $school = $this->moveLogo($school, $path);
        }

        return $school;
    }

    public function deleteSchools($ids)
    {
        $query = School::whereIn('id', $ids)
            ->doesntHave('registers')
            ->doesntHave('licences')
            ->doesntHave('users');

        // grab the IDs before deleting (so we can report back)
        $deletableIds = (clone $query)->pluck('id')->all();

        DB::transaction(function () use ($query) {
            $query->delete(); // hard delete; use ->forceDelete() if using SoftDeletes and you want to bypass soft deletion
        });

        $skippedIds = array_values(array_diff($ids, $deletableIds));

        // Optional: return/report what happened
        return [
            'deleted' => $deletableIds,
            'skipped' => $skippedIds,
        ];
    }

    private function moveLogo($school, $path)
    {
        $relPath = Str::before(ltrim($path, '/'), '?'); // strip leading slash + ?t=...

        // 1) Absolute paths
        $source = storage_path('app/public/' . $relPath);      // /storage/app/private/temp/1/logo.jpg
        $destDir = storage_path('app/public/images');           // /storage/app/public/images

        // 2) Build new filename
        $baseName  = pathinfo($source, PATHINFO_FILENAME);      // "logo"
        $extension = pathinfo($source, PATHINFO_EXTENSION);     // "jpg"
        $newFilename = "{$baseName}_{$school->id}.{$extension}";  // "logo_12.jpg"
        $destPath = $destDir . DIRECTORY_SEPARATOR . $newFilename;


        // 3) copy the file
        // make sure the target directory exists
        File::ensureDirectoryExists(dirname($destDir));

        // copy the file
        File::copy($source, $destPath);

        // 4) Save only the pure filename in DB
        $school->logo = $newFilename;   // e.g. "logo_12.jpg"
        $school->save();

        return $school;
    }
}
