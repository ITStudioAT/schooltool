<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialUnit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'topic_id',
        'name',
        'sort_order',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(MaterialTopic::class, 'topic_id');
    }
}
