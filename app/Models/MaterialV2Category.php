<?php

namespace App\Models;

use Database\Factories\MaterialV2CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialV2Category extends Model
{
    /** @use HasFactory<MaterialV2CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'name',
        'normalized_name',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
