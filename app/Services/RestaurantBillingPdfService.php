<?php

namespace App\Services;

use App\Models\RestaurantBilling;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RestaurantBillingPdfService
{
    public function createPdf(RestaurantBilling $billing): string
    {
        $billing->loadMissing('school');

        $path = $this->pdfDirectory().DIRECTORY_SEPARATOR.$this->filename($billing);
        $snapshot = is_array($billing->snapshot) ? $billing->snapshot : [];

        DomPdf::loadView('pdfs.restaurantBilling', [
            'billing' => [
                'title' => 'Abrechnung',
                'period_label' => $this->periodLabel($billing->start_date, $billing->end_date),
                'range_label' => $this->dateRangeLabel($billing->start_date, $billing->end_date),
                'school_name' => (string) ($billing->school?->long_name ?: $billing->school?->short_name ?: ''),
                'created_at' => $billing->created_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i'),
                'bookings_count' => (int) $billing->bookings_count,
            ],
            'rows' => $snapshot['rows'] ?? [],
            'overallTotalLabel' => (string) ($snapshot['overall_total_amount_label'] ?? $this->formatPrice($billing->total_amount)),
            'overallQuantity' => (int) ($snapshot['overall_total_quantity'] ?? $billing->bookings_count),
        ])
            ->setPaper('a4', 'portrait')
            ->save($path);

        return $path;
    }

    private function filename(RestaurantBilling $billing): string
    {
        $period = Str::slug($this->periodLabel($billing->start_date, $billing->end_date), '_');

        return 'abrechnung_'.$period.'_'.now()->format('Ymd_His').'.pdf';
    }

    private function pdfDirectory(): string
    {
        $directory = storage_path('app/private/pdf');
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function periodLabel(?Carbon $startDate, ?Carbon $endDate): string
    {
        if (! $startDate || ! $endDate) {
            return 'KW --';
        }

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

    private function dateRangeLabel(?Carbon $startDate, ?Carbon $endDate): string
    {
        return trim(implode(' - ', array_filter([
            $startDate?->format('d.m.Y'),
            $endDate?->format('d.m.Y'),
        ])));
    }

    private function formatPrice(mixed $price): string
    {
        return number_format((float) $price, 2, ',', '.').' €';
    }
}
