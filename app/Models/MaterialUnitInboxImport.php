<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialUnitInboxImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_user_id',
        'target_unit_id',
        'source_rule_id',
        'source_school_id',
        'source_unit_id',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class, 'target_unit_id');
    }

    public function sourceRule(): BelongsTo
    {
        return $this->belongsTo(MaterialShareRule::class, 'source_rule_id');
    }
}
