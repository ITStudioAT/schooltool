<?php

namespace App\Exceptions;

class DropboxFolderCreationFailed extends DropboxExerciseRequestFailed
{
    public function __construct(string $tag, ?int $httpStatus = null)
    {
        parent::__construct('folder_create', $tag, $httpStatus);
    }
}
