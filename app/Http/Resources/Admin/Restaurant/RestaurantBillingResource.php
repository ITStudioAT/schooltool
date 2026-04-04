<?php

namespace App\Http\Resources\Admin\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class RestaurantBillingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startDate = $this->start_date instanceof Carbon ? $this->start_date : Carbon::parse((string) $this->start_date);
        $endDate = $this->end_date instanceof Carbon ? $this->end_date : Carbon::parse((string) $this->end_date);

        return [
            'id' => $this->id,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'weeks_count' => (int) $this->weeks_count,
            'bookings_count' => (int) $this->bookings_count,
            'total_amount' => $this->total_amount !== null ? number_format((float) $this->total_amount, 2, '.', '') : '0.00',
            'period_label' => $this->periodLabel($startDate, $endDate),
            'date_range_label' => $startDate->format('d.m.Y').' - '.$endDate->format('d.m.Y'),
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
        ];
    }

    private function periodLabel(Carbon $startDate, Carbon $endDate): string
    {
        $startWeek = $startDate->isoWeek();
        $endWeek = $endDate->isoWeek();
        $startYear = $startDate->isoWeekYear();
        $endYear = $endDate->isoWeekYear();

        if ($startWeek === $endWeek && $startYear === $endYear) {
            return 'KW '.str_pad((string) $startWeek, 2, '0', STR_PAD_LEFT).'/'.$startYear;
        }

        if ($startYear === $endYear) {
            return 'KW '.str_pad((string) $startWeek, 2, '0', STR_PAD_LEFT)
                .'-'.str_pad((string) $endWeek, 2, '0', STR_PAD_LEFT)
                .'/'.$startYear;
        }

        return 'KW '.str_pad((string) $startWeek, 2, '0', STR_PAD_LEFT).'/'.$startYear
            .' - KW '.str_pad((string) $endWeek, 2, '0', STR_PAD_LEFT).'/'.$endYear;
    }
}
