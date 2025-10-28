<?php

namespace App\Services;

use Intervention\Image\Image;
use Intervention\Image\ImageManager;






class FileXXXUploadService
{
    public function upload()
    {
        file_put_contents(storage_path('app/private/temp/file.part'), '');
    }

    public function uploadNext($request, $upload_path, $new_name = null, $fit = null)
    {
        // Parameter:
        // upload_path, Unterverzeichnis in storage/app/public, wenn nicht vorhanden wird es erzeugt
        // $new_name, Filename. Wenn nicht angegeben wird das Original verwendet
        // $fit['width'=>x, 'height'=>y], es kann auch nur ein Array-Key angegeben werden. Image wird auf die Größe angepasst


        $upload_path = trim($upload_path, '/');

        $path = storage_path('app/private/temp/' . $request->query('patch') . '/file.part');
        $name = $request->header('Upload-Name');
        $extension = pathinfo($name, PATHINFO_EXTENSION);


        file_put_contents($path, $request->getContent(), FILE_APPEND);
        $fs = filesize($path);

        if ($fs == $request->header('Upload-Length')) {

            // Falls ein Dateiname explizit als Parameter angegeben wurde
            if ($new_name) {
                $name = $new_name . '.' . $extension;
            }

            if (!is_dir(storage_path($upload_path))) mkdir(storage_path($upload_path));

            $new_path = storage_path($upload_path . '/' . $name);
            rename($path, $new_path);

            /*
            $manager = ImageManager::gd();
            $image = $manager->read($new_path);
            $image = $image->scale(200, 100);
            $image->save($new_path);
*/
            // Größe anpassen
            /*
            if ($fit && is_array($fit)) {
                $manager = ImageManager::gd();
                $image = $manager->read($new_path);


                if (array_key_exists('width', $fit)  && array_key_exists('height', $fit)) {
                    $image = $image->scale($fit['width'],  $fit['height']);
                } elseif (array_key_exists('width', $fit)) {
                    $image = $image->scale(width: $fit['width']);
                } elseif (array_key_exists('height', $fit)) {
                    $image = $image->scale(height: $fit['height']);
                }
                $image->save();
            }
*/


            return $name;
        } else {
            abort(500, "Fehler beim Upload!");
        }
    }
}
