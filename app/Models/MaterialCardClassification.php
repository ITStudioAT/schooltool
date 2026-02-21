<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialCardClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_card_id',
        'subject_id',
        'topic_id',
        'unit_id',
    ];

    public function materialCard(): BelongsTo
    {
        return $this->belongsTo(MaterialCard::class, 'material_card_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(MaterialSubject::class, 'subject_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(MaterialTopic::class, 'topic_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class, 'unit_id');
    }
}

