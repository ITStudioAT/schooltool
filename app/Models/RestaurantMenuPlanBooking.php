<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantMenuPlanBooking extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'user_id',
        'restaurant_menu_plan_entry_id',
        'restaurant_eating_time_id',
        'price',
        'quantity',
        'total_price',
        'child_name',
        'child_type',
        'import116_id',
        'booked_at',
        'notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
        'booked_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'booked_at',
    ];

    /**
     * Get the school that owns the booking.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user that made the booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the menu plan entry that was booked.
     */
    public function menuPlanEntry(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuPlanEntry::class, 'restaurant_menu_plan_entry_id');
    }

    /**
     * Get the eating time for the booking.
     */
    public function eatingTime(): BelongsTo
    {
        return $this->belongsTo(RestaurantEatingTime::class, 'restaurant_eating_time_id');
    }

    /**
     * Get the import116 record if applicable.
     */
    public function import116(): BelongsTo
    {
        return $this->belongsTo(Import116::class, 'import116_id');
    }

    /**
     * Calculate the total price before saving.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $booking): void {
            if ($booking->price !== null && $booking->quantity !== null) {
                $booking->total_price = $booking->price * $booking->quantity;
            }
        });
    }

    /**
     * Check if this booking is for a child.
     */
    public function isForChild(): bool
    {
        return $this->child_type === 'child';
    }

    /**
     * Check if this booking is for another person.
     */
    public function isForOtherPerson(): bool
    {
        return $this->child_type === 'other_person';
    }

    /**
     * Get the display name for whom the menu is ordered.
     */
    public function getOrderedForDisplayAttribute(): string
    {
        if ($this->child_name) {
            return $this->child_name;
        }

        return $this->user?->full_name ?? 'Unbekannt';
    }
}
