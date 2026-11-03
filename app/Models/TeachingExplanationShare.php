<?php

namespace App\Models;

use Database\Factories\TeachingExplanationShareFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingExplanationShare extends Model
{
    /** @use HasFactory<TeachingExplanationShareFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'user_id', 'explanation', 'token', 'access_mode', 'is_active', 'published_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'published_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
