<?php

namespace App\Models;

use Database\Factories\RestaurantFoodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RestaurantFood extends Model
{
    /** @use HasFactory<RestaurantFoodFactory> */
    use HasFactory;

    protected $table = 'restaurant_foods';

    protected $fillable = [
        'school_id',
        'user_id',
        'restaurant_category_id',
        'title',
        'description',
        'allergens',
        'price',
        'food_image_path',
    ];

    protected function casts(): array
    {
        return [
            'allergens' => 'array',
            'price' => 'decimal:2',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RestaurantCategory::class, 'restaurant_category_id');
    }

    public function ingredientIcons(): BelongsToMany
    {
        return $this->belongsToMany(
            RestaurantIngredientIcon::class,
            'restaurant_food_restaurant_ingredient_icon'
        );
    }
}
