<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaturaAccess extends Model
{
    protected $fillable = ['matura_session_id', 'matura_room_id', 'user_id', 'name', 'token_hash', 'expires_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(MaturaSession::class, 'matura_session_id');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
