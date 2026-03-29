<?php

namespace App\Models;

use Database\Factories\RestaurantMenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RestaurantMenu extends Model
{
    /** @use HasFactory<RestaurantMenuFactory> */
    use HasFactory;

    protected $table = 'restaurant_menus';

    protected $fillable = [
        'school_id',
        'legacy_menu_id',
        'title',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function foods(): BelongsToMany
    {
        return $this->belongsToMany(
            RestaurantFood::class,
            'restaurant_food_restaurant_menu'
        )->withPivot('course_number')->withTimestamps()->orderByPivot('course_number');
    }
}
