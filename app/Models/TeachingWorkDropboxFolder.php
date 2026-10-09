<?php

namespace App\Models;

use Database\Factories\TeachingWorkDropboxFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeachingWorkDropboxFolder extends Model
{
    /** @use HasFactory<TeachingWorkDropboxFolderFactory> */
    use HasFactory;

    protected $fillable = ['dropbox_connection_id', 'teaching_course_work_id', 'folder_id', 'folder_name'];
}
