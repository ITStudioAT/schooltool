<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaturaEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'details' => 'array'];
    }
}
