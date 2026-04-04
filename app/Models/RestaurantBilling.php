<?php

namespace App\Models;

use Database\Factories\RestaurantBillingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantBilling extends Model
{
    /** @use HasFactory<RestaurantBillingFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'created_by_user_id',
        'start_date',
        'end_date',
        'weeks_count',
        'bookings_count',
        'total_amount',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'weeks_count' => 'integer',
            'bookings_count' => 'integer',
            'total_amount' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
