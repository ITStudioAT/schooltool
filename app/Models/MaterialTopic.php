<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'name',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(MaterialSubject::class, 'subject_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(MaterialUnit::class, 'topic_id')->orderBy('name');
    }
}

