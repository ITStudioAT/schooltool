<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialTopic extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'subject_id',
        'name',
        'sort_order',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(MaterialSubject::class, 'subject_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(MaterialUnit::class, 'topic_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }
}
