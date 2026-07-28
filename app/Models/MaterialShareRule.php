<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialShareRule extends Model
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_SUBJECT = 'subject';

    public const SCOPE_TOPIC = 'topic';

    public const SCOPE_UNIT = 'unit';

    public const SCOPE_MATERIAL = 'material';

    public const SCOPES = [
        self::SCOPE_ALL,
        self::SCOPE_SUBJECT,
        self::SCOPE_TOPIC,
        self::SCOPE_UNIT,
        self::SCOPE_MATERIAL,
    ];

    protected $fillable = [
        'school_id',
        'created_by_user_id',
        'workspace_id',
        'scope_type',
        'scope_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (MaterialShareRule $rule): void {
            if ((int) ($rule->workspace_id ?? 0) <= 0 && (int) ($rule->created_by_user_id ?? 0) > 0) {
                $workspace = MaterialWorkspace::query()->firstOrCreate(
                    [
                        'user_id' => (int) $rule->created_by_user_id,
                        'name' => 'Workspace',
                    ],
                    [
                        'is_default' => true,
                    ]
                );

                $rule->workspace_id = (int) $workspace->id;
            }

            $rule->natural_key = $rule->naturalKey();
        });

        static::updating(function (MaterialShareRule $rule): void {
            $rule->natural_key = $rule->naturalKey();
        });
    }

    public function naturalKey(): string
    {
        return implode(':', [
            (int) $this->school_id,
            (int) ($this->created_by_user_id ?? 0),
            (int) ($this->workspace_id ?? 0),
            (string) $this->scope_type,
            (int) ($this->scope_id ?? 0),
        ]);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MaterialShareTarget::class, 'material_share_rule_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(MaterialWorkspace::class, 'workspace_id');
    }
}
