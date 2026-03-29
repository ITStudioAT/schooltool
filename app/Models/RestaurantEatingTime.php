<?php

namespace App\Models;

use Database\Factories\RestaurantEatingTimeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantEatingTime extends Model
{
    /** @use HasFactory<RestaurantEatingTimeFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'eating_time',
    ];

    protected function casts(): array
    {
        return [
            'eating_time' => 'string',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
