<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingCourseDateMaterialAttachment extends Model
{
    protected $fillable = [
        'teaching_course_date_material_id',
        'source_material_card_attachment_id',
        'source_teaching_curriculum_document_id',
        'name',
        'file_path',
        'mime_type',
        'size_bytes',
        'student_visible',
    ];

    protected function casts(): array
    {
        return [
            'student_visible' => 'boolean',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(TeachingCourseDateMaterial::class, 'teaching_course_date_material_id');
    }

    public function sourceAttachment(): BelongsTo
    {
        return $this->belongsTo(MaterialCardAttachment::class, 'source_material_card_attachment_id');
    }
}
