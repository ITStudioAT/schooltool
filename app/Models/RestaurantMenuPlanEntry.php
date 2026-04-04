<?php

namespace App\Models;

use Database\Factories\RestaurantMenuPlanEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantMenuPlanEntry extends Model
{
    /** @use HasFactory<RestaurantMenuPlanEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_menu_plan_id',
        'plan_date',
        'restaurant_menu_id',
        'menu_title',
        'price',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function menuPlan(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuPlan::class, 'restaurant_menu_plan_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenu::class, 'restaurant_menu_id');
    }

    public function eatingTimes(): BelongsToMany
    {
        return $this->belongsToMany(
            RestaurantEatingTime::class,
            'restaurant_menu_plan_entry_eating_times',
            'restaurant_menu_plan_entry_id',
            'restaurant_eating_time_id'
        );
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RestaurantMenuPlanBooking::class, 'restaurant_menu_plan_entry_id');
    }
}
