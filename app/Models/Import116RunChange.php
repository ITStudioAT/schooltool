<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import116RunChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'import116_run_id',
        'school_id',
        'schoolyear_id',
        'student_code',
        'change_type',
        'before_snapshot',
        'after_snapshot',
        'summary',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'summary' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Import116Run::class, 'import116_run_id');
    }
}
