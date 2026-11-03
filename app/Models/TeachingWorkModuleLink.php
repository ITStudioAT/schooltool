<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeachingWorkModuleLink extends Model
{
    protected $fillable = ['dropbox_connection_id', 'teaching_course_work_id', 'module_id', 'schema_version',
        'account_id', 'namespace_id', 'folder_id', 'folder_name', 'folder_path', 'handshake_file_id', 'handshake_revision'];
}
