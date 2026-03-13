<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbaAnalysisResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'aba_id',
        'aba_analysis_run_id',
        'aba_attachment_id',
        'parent_result_id',
        'section_type',
        'section_title',
        'extracted_text',
        'sort_order',
        'hierarchy_level',
        'start_line',
        'end_line',
        'start_page',
        'end_page',
        'anchor',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'hierarchy_level' => 'integer',
            'start_line' => 'integer',
            'end_line' => 'integer',
            'start_page' => 'integer',
            'end_page' => 'integer',
            'anchor' => 'array',
            'metadata' => 'array',
        ];
    }

    public function aba(): BelongsTo
    {
        return $this->belongsTo(Aba::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AbaAnalysisRun::class, 'aba_analysis_run_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(AbaAttachment::class, 'aba_attachment_id')->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_result_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_result_id')->orderBy('sort_order');
    }
}
