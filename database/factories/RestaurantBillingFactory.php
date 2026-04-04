<?php

namespace Database\Factories;

use App\Models\RestaurantBilling;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantBilling>
 */
class RestaurantBillingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->startOfWeek()->subWeeks(2);
        $endDate = $startDate->copy()->endOfWeek();

        return [
            'school_id' => School::factory(),
            'created_by_user_id' => null,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'weeks_count' => 1,
            'bookings_count' => 0,
            'total_amount' => '0.00',
            'snapshot' => [
                'rows' => [],
                'overall_total_amount' => '0.00',
                'overall_total_quantity' => 0,
            ],
        ];
    }
}
