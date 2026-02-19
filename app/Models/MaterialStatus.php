<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStatus extends Model
{
    protected $fillable = [
        'school_id',
        'value',
        'label',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
