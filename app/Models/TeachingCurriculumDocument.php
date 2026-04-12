<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCurriculumDocument extends Model
{
    protected $fillable = [
        'teaching_curriculum_id',
        'source_type',
        'name',
        'file_path',
        'mime_type',
        'size_bytes',
        'material_card_id',
    ];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(TeachingCurriculum::class, 'teaching_curriculum_id');
    }

    public function materialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class);
    }
}
