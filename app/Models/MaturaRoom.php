<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaturaRoom extends Model
{
    protected $fillable = ['matura_session_id', 'name', 'supervisor_access_id'];

    public function students(): HasMany
    {
        return $this->hasMany(MaturaStudent::class);
    }
}
