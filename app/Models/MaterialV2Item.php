<?php

namespace App\Models;

use Database\Factories\MaterialV2ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialV2Item extends Model
{
    /** @use HasFactory<MaterialV2ItemFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id',
        'user_id',
        'title',
        'category',
        'description',
        'user_keywords',
        'generated_keywords',
        'search_text',
        'processing_status',
        'processing_error',
        'processing_started_at',
        'processed_at',
    ];

    protected $attributes = [
        'processing_status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'user_keywords' => 'array',
            'generated_keywords' => 'array',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaterialV2Attachment::class)->latest('id');
    }
}
