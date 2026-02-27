<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialCardDeletedClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_card_id',
        'subject_name',
        'topic_name',
        'unit_name',
    ];

    public function materialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class, 'material_card_id');
    }
}
