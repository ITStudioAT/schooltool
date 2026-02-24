<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialShareTarget extends Model
{
    public const TARGET_EVERYONE = 'everyone';
    public const TARGET_GROUP = 'group';
    public const TARGET_USER = 'user';

    public const TARGETS = [
        self::TARGET_EVERYONE,
        self::TARGET_GROUP,
        self::TARGET_USER,
    ];

    protected $fillable = [
        'material_share_rule_id',
        'target_type',
        'user_group_id',
        'user_id',
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
