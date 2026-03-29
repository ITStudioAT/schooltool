<?php

namespace App\Models;

use Database\Factories\RestaurantMenuPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantMenuPlan extends Model
{
    /** @use HasFactory<RestaurantMenuPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'title',
        'start_date',
        'end_date',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RestaurantMenuPlanEntry::class)->orderBy('plan_date');
    }
}
