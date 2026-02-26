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
