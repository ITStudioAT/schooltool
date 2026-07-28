<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialShareTarget extends Model
{
    public const TARGET_EVERYONE = 'everyone';

    public const TARGET_GROUP = 'group';

    public const TARGET_USER = 'user';

    public const AUDIENCE_SCOPE_SCHOOL = 'school';

    public const AUDIENCE_SCOPE_GLOBAL = 'global';

    public const PERMISSION_FULL_ACCESS = 'full_access';

    public const PERMISSION_READ_WRITE = 'read_write';

    public const PERMISSION_READ_APPEND = 'read_append';

    public const PERMISSION_READ_ONLY = 'read_only';

    public const TARGETS = [
        self::TARGET_EVERYONE,
        self::TARGET_GROUP,
        self::TARGET_USER,
    ];

    public const AUDIENCE_SCOPES = [
        self::AUDIENCE_SCOPE_SCHOOL,
        self::AUDIENCE_SCOPE_GLOBAL,
    ];

    public const PERMISSIONS = [
        self::PERMISSION_FULL_ACCESS,
        self::PERMISSION_READ_WRITE,
        self::PERMISSION_READ_APPEND,
        self::PERMISSION_READ_ONLY,
    ];

    protected $fillable = [
        'material_share_rule_id',
        'target_type',
        'audience_scope',
        'permission',
        'user_group_id',
        'user_id',
    ];

    protected $attributes = [
        'permission' => self::PERMISSION_READ_ONLY,
    ];

    protected static function booted(): void
    {
        static::saving(function (MaterialShareTarget $target): void {
            $target->natural_key = $target->naturalKey();
        });
    }

    public function naturalKey(): string
    {
        $targetIdentity = match ((string) $this->target_type) {
            self::TARGET_EVERYONE => 'everyone:'.(string) ($this->audience_scope ?? ''),
            self::TARGET_USER => 'user:'.(int) ($this->user_id ?? 0),
            self::TARGET_GROUP => 'group:'.(int) ($this->user_group_id ?? 0),
            default => (string) $this->target_type.':',
        };

        return (int) $this->material_share_rule_id.':'.$targetIdentity;
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MaterialShareRule::class, 'material_share_rule_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
