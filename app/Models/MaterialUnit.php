<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'name',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(MaterialTopic::class, 'topic_id');
    }
}

