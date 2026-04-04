<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'schoolyear_id',
        'user_id',
        'scope',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
