<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialV2TagSuggestion extends Model
{
    protected $fillable = [
        'material_v2_item_id',
        'material_v2_attachment_id',
        'tag_name',
        'normalized_name',
        'base_score',
        'final_score',
        'rank',
        'algorithm',
        'language',
        'source_locations',
        'source_hash',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'base_score' => 'float',
            'final_score' => 'float',
            'rank' => 'integer',
            'source_locations' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MaterialV2Item::class, 'material_v2_item_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(MaterialV2Attachment::class, 'material_v2_attachment_id');
    }
}
