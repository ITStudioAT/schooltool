<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaturaStudent extends Model
{
    protected $fillable = ['matura_session_id', 'matura_room_id', 'import116_id', 'name', 'class_name'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(MaturaRoom::class, 'matura_room_id');
    }
}
