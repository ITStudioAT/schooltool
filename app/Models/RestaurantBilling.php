<?php

namespace App\Models;

use Database\Factories\RestaurantBillingFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /** @return list<string> */
    public function coveredWeekStarts(): array
    {
        $selected = $this->snapshot['week_starts'] ?? null;
        if (is_array($selected) && $selected !== []) {
            return array_values($selected);
        }
        $weeks = [];
        $cursor = $this->start_date->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lte($this->end_date)) {
            $weeks[] = $cursor->toDateString();
            $cursor->addWeek();
        }

        return $weeks;
    }

    public function coversDate(DateTimeInterface|string $date): bool
    {
        $day = Carbon::parse($date);
        if (! empty($this->snapshot['week_starts'])) {
            return in_array($day->startOfWeek(Carbon::MONDAY)->toDateString(), $this->coveredWeekStarts(), true);
        }

        return $day->betweenIncluded($this->start_date->copy()->startOfDay(), $this->end_date->copy()->endOfDay());
    }

    public function coversPeriod(DateTimeInterface|string $start, DateTimeInterface|string $end): bool
    {
        $from = Carbon::parse($start)->startOfDay();
        $until = Carbon::parse($end)->endOfDay();
        if (! empty($this->snapshot['week_starts'])) {
            foreach ($this->coveredWeekStarts() as $week) {
                $weekStart = Carbon::parse($week)->startOfDay();
                if ($weekStart->lte($until) && $weekStart->copy()->addDays(6)->endOfDay()->gte($from)) {
                    return true;
                }
            }

            return false;
        }

        return $this->start_date->lte($until) && $this->end_date->copy()->endOfDay()->gte($from);
    }

    public function scopeCoveringDate(Builder $query, DateTimeInterface|string $date): void
    {
        $day = Carbon::parse($date);
        $query->where(function (Builder $billing) use ($day): void {
            $billing->whereJsonContains('snapshot->week_starts', $day->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('snapshot->week_starts')
                    ->whereDate('start_date', '<=', $day->toDateString())->whereDate('end_date', '>=', $day->toDateString()));
        });
    }

    public function selectedWeeksLabel(): ?string
    {
        if (empty($this->snapshot['week_starts'])) {
            return null;
        }

        return collect($this->coveredWeekStarts())->map(fn (string $week): string => 'KW '.Carbon::parse($week)->format('W/o'))->implode(' · ');
    }

    public function selectedDatesLabel(): ?string
    {
        if (empty($this->snapshot['week_starts'])) {
            return null;
        }

        return collect($this->coveredWeekStarts())->map(function (string $week): string {
            $start = Carbon::parse($week);

            return $start->format('d.m.Y').' – '.$start->addDays(6)->format('d.m.Y');
        })->implode(' · ');
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
