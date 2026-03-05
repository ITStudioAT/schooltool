<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialShareRuleArchive extends Model
{
    protected $fillable = [
        'target_user_id',
        'material_share_rule_id',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MaterialShareRule::class, 'material_share_rule_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
