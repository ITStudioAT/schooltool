<?php

namespace App\Models;

use Database\Factories\RestaurantIngredientIconFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RestaurantIngredientIcon extends Model
{
    /** @use HasFactory<RestaurantIngredientIconFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'title',
        'image_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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
            'restaurant_food_restaurant_ingredient_icon'
        );
    }
}
