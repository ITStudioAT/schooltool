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
        'visible_start_at',
        'visible_end_at',
        'order_start_at',
        'order_end_at',
        'use_individual_schedule_values',
        'visibility_start_mode',
        'visibility_start_week_offset',
        'visibility_start_day_of_week',
        'visibility_start_time',
        'order_start_mode',
        'order_start_week_offset',
        'order_start_day_of_week',
        'order_start_time',
        'order_end_week_offset',
        'order_end_day_of_week',
        'order_end_time',
        'visibility_end_mode',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_available' => 'boolean',
            'visible_start_at' => 'datetime',
            'visible_end_at' => 'datetime',
            'order_start_at' => 'datetime',
            'order_end_at' => 'datetime',
            'use_individual_schedule_values' => 'boolean',
            'visibility_start_week_offset' => 'integer',
            'visibility_start_day_of_week' => 'integer',
            'order_start_week_offset' => 'integer',
            'order_start_day_of_week' => 'integer',
            'order_end_week_offset' => 'integer',
            'order_end_day_of_week' => 'integer',
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
