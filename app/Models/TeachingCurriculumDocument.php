<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCurriculumDocument extends Model
{
    protected $fillable = [
        'teaching_curriculum_id',
        'topic_id',
        'unit_id',
        'source_type',
        'name',
        'file_path',
        'storage_disk',
        'mime_type',
        'size_bytes',
        'material_card_id',
        'material_card_attachment_id',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(TeachingCurriculum::class, 'teaching_curriculum_id');
    }

    /** @return BelongsTo<MaterialCard, $this> */
    public function materialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class);
    }

    public function materialAttachment(): BelongsTo
    {
        return $this->belongsTo(MaterialCardAttachment::class, 'material_card_attachment_id');
    }
}
