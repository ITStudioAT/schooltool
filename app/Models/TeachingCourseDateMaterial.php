<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingCourseDateMaterial extends Model
{
    protected $fillable = [
        'teaching_course_date_id',
        'title',
        'material_title',
        'type',
        'status',
        'subject',
        'area',
        'unit',
        'source_material_card_id',
    ];

    public function courseDate(): BelongsTo
    {
        return $this->belongsTo(TeachingCourseDate::class, 'teaching_course_date_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TeachingCourseDateMaterialAttachment::class);
    }
}
