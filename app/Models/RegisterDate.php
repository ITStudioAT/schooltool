<?php

namespace App\Models;

use App\Models\RegisterDateBooking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegisterDate extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    public function bookings(): HasMany
    {
        return $this->hasMany(RegisterDateBooking::class);
    }
}
