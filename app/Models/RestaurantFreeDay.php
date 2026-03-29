<?php

namespace App\Models;

use Database\Factories\RestaurantFreeDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantFreeDay extends Model
{
    /** @use HasFactory<RestaurantFreeDayFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'free_date',
    ];

    protected function casts(): array
    {
        return [
            'free_date' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
