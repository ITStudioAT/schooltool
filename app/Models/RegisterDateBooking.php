<?php

namespace App\Models;

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterDateBooking extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerDate(): BelongsTo
    {
        return $this->belongsTo(RegisterDate::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
