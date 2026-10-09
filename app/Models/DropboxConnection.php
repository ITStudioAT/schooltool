<?php

namespace App\Models;

use Database\Factories\DropboxConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DropboxConnection extends Model
{
    /** @use HasFactory<DropboxConnectionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'account_id', 'credentials', 'revoked_at'];

    protected $hidden = ['credentials', 'account_id'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'revoked_at' => 'datetime'];
    }
}
