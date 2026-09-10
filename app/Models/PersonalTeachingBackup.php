<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalTeachingBackup extends Model
{
    protected $fillable = ['user_id', 'school_id', 'payload', 'summary', 'mail_status', 'mail_message', 'mailed_at', 'recovery_requested_at', 'student_mappings', 'import_mappings'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'summary' => 'array',
            'mailed_at' => 'datetime',
            'recovery_requested_at' => 'datetime',
            'student_mappings' => 'array',
            'import_mappings' => 'array',
        ];
    }
}
