<?php

namespace App\Models;

use App\Models\Register;
use Illuminate\Database\Eloquent\Model;

class Schoolyear extends Model
{
    protected $guarded = [];


    public function hasDependencies(): bool
    {
        // Prüfen, ob es Registers gibt
        if (Register::where('schoolyear_id', $this->id)->exists())  return true;
        return false;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
