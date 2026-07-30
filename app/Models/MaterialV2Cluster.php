<?php

namespace App\Models;

use Database\Factories\MaterialV2ClusterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialV2Cluster extends Model
{
    /** @use HasFactory<MaterialV2ClusterFactory> */
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

    public function items(): HasMany
    {
        return $this->hasMany(MaterialV2Item::class)->latest();
    }
}
