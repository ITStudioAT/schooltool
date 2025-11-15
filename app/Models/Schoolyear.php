<?php

namespace App\Models;

use App\Models\Register;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schoolyear extends Model
{
    use HasFactory;
    
    protected $guarded = [];


    public function hasDependencies(): bool
    {
        // Prüfen, ob es Registers gibt
        if (Register::where('schoolyear_id', $this->id)->exists())  return true;

        // Prüfen, ob es mehr als einen Uas
        if (User::where('schoolyear_id', $this->id)->exists())  return true;
        return false;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
