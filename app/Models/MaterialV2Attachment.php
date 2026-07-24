<?php

namespace App\Models;

use Database\Factories\MaterialV2AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialV2Attachment extends Model
{
    /** @use HasFactory<MaterialV2AttachmentFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_UNSUPPORTED = 'unsupported';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'material_v2_item_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'extracted_text',
        'extraction_status',
        'extraction_error',
        'extracted_at',
    ];

    protected $attributes = [
        'extraction_status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'extracted_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MaterialV2Item::class, 'material_v2_item_id');
    }
}
