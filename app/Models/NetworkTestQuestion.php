<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkTestQuestion extends Model
{
    protected $fillable = ['school_id', 'user_id', 'question', 'answer'];

    protected $visible = ['id', 'question', 'answer'];
}
