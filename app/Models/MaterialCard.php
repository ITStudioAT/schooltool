<?php

namespace App\Models;

use App\Support\SafeExternalUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialCard extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_INBOX = 'inbox';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_UPDATE_NEEDED = 'update_needed';

    protected $fillable = [
        'school_id',
        'user_id',
        'workspace_id',
        'title',
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

    protected function sourceUrl(): Attribute
    {
        return Attribute::get(
            fn (?string $value): ?string => SafeExternalUrl::sanitize($value)
        );
    }

    protected static function booted(): void
    {
        static::creating(function (MaterialCard $card): void {
            if ((int) ($card->workspace_id ?? 0) > 0 || (int) ($card->user_id ?? 0) <= 0) {
                return;
            }

            $workspace = MaterialWorkspace::query()->firstOrCreate(
                [
                    'user_id' => (int) $card->user_id,
                    'name' => 'Workspace',
                ],
                [
                    'is_default' => true,
                ]
            );

            $card->workspace_id = (int) $workspace->id;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(MaterialWorkspace::class, 'workspace_id');
    }

    /** @return HasMany<MaterialCardAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MaterialCardAttachment::class)->orderByDesc('created_at');
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(MaterialCardClassification::class, 'material_card_id');
    }

    public function inboxImports(): HasMany
    {
        return $this->hasMany(MaterialInboxImport::class, 'target_material_card_id')->orderByDesc('id');
    }

    public static function statusValues(): array
    {
        return [
            self::STATUS_INBOX,
            self::STATUS_IN_PROGRESS,
            self::STATUS_DONE,
            self::STATUS_UPDATE_NEEDED,
        ];
    }
}
