<?php

namespace App\Models;

use Database\Factories\MaterialV2ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

class MaterialV2Item extends Model
{
    /** @use HasFactory<MaterialV2ItemFactory> */
    use HasFactory;

    use Searchable;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id',
        'user_id',
        'material_v2_cluster_id',
        'title',
        'category',
        'description',
        'reminder_date',
        'reminder_time',
        'link_url',
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
            'reminder_date' => 'date:Y-m-d',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[SearchUsingFullText(['title', 'description', 'search_text'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'reminder_date' => $this->reminder_date?->format('Y-m-d'),
            'link_url' => $this->link_url,
            'user_keywords' => $this->user_keywords,
            'generated_keywords' => $this->generated_keywords,
            'search_text' => $this->search_text,
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

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(MaterialV2Cluster::class, 'material_v2_cluster_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaterialV2Attachment::class)->latest('id');
    }

    public function automaticTagSuggestions(): HasMany
    {
        return $this->hasMany(MaterialV2TagSuggestion::class)
            ->whereNull('dismissed_at')
            ->orderByDesc('final_score')
            ->orderBy('rank');
    }
}
