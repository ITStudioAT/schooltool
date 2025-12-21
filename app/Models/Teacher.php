<?php

namespace App\Models;

use App\Models\School;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $guarded = [];

    public function setToken($minutes): string
    {
        $this->token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->token_expires_at = now()->addMinutes($minutes);
        $this->save();
        return $this->token;
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
