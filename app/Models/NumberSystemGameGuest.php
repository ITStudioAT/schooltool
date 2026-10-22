<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSystemGameGuest extends Model
{
    protected $fillable = ['session_id', 'run_id', 'first_name', 'token_hash', 'last_seen_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
