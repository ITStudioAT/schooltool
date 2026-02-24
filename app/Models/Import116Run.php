<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import116Run extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'source_path',
        'status',
        'started_at',
        'finished_at',
        'undone_at',
        'undone_by_user_id',
        'counts',
        'report_summary',
        'report_paths',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'undone_at' => 'datetime',
        'counts' => 'array',
        'report_summary' => 'array',
        'report_paths' => 'array',
    ];

    public function changes(): HasMany
    {
        return $this->hasMany(Import116RunChange::class, 'import116_run_id');
    }
}
