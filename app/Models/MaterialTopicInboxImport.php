<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialTopicInboxImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_user_id',
        'target_topic_id',
        'source_rule_id',
        'source_school_id',
        'source_topic_id',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetTopic(): BelongsTo
    {
        return $this->belongsTo(MaterialTopic::class, 'target_topic_id');
    }

    public function sourceRule(): BelongsTo
    {
        return $this->belongsTo(MaterialShareRule::class, 'source_rule_id');
    }
}
