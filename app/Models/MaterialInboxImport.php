<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialInboxImport extends Model
{
    use HasFactory;

    public const MODE_COPY = 'copy';
    public const MODE_LINK = 'link';

    protected $fillable = [
        'target_user_id',
        'target_material_card_id',
        'source_rule_id',
        'source_school_id',
        'source_material_id',
        'import_mode',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetMaterialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class, 'target_material_card_id');
    }

    public function sourceRule(): BelongsTo
    {
        return $this->belongsTo(MaterialShareRule::class, 'source_rule_id');
    }
}
