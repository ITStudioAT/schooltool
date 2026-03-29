<?php

namespace App\Models;

use Database\Factories\RestaurantCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantCategory extends Model
{
    /** @use HasFactory<RestaurantCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'title',
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

    public function foods(): HasMany
    {
        return $this->hasMany(RestaurantFood::class);
    }
}
