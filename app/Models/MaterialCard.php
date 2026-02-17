<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCard extends Model
{
    use HasFactory;

    public const STATUS_INBOX = 'inbox';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';

    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_LINK = 'link';
    public const SOURCE_NOTE = 'note';

    protected $fillable = [
        'school_id',
        'user_id',
        'title',
        'source_type',
        'source_url',
        'source_text',
        'subject',
        'area',
        'unit',
        'type',
        'status',
        'notes',
        'keywords',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaterialCardAttachment::class)->orderByDesc('created_at');
    }

    public static function statusValues(): array
    {
        return [
            self::STATUS_INBOX,
            self::STATUS_IN_PROGRESS,
            self::STATUS_DONE,
        ];
    }

    public static function sourceValues(): array
    {
        return [
            self::SOURCE_UPLOAD,
            self::SOURCE_LINK,
            self::SOURCE_NOTE,
        ];
    }
}
