<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingBackupRestoreRun extends Model
{
    protected $fillable = [
        'teaching_backup_id',
        'pre_restore_backup_id',
        'school_id',
        'schoolyear_id',
        'user_id',
        'type',
        'status',
        'progress_current',
        'progress_total',
        'selection',
        'result',
        'audit_metadata',
        'message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'selection' => 'array',
            'result' => 'array',
            'audit_metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(TeachingBackup::class, 'teaching_backup_id');
    }

    public function preRestoreBackup(): BelongsTo
    {
        return $this->belongsTo(TeachingBackup::class, 'pre_restore_backup_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolyear(): BelongsTo
    {
        return $this->belongsTo(Schoolyear::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
