<?php

namespace App\Services;

use App\Models\RegisterDate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;


class RegisterDateService
{


    public function loadDays($register_id): array
    {
        Carbon::setLocale(app()->getLocale()); // or explicitly 'de', 'fr', etc.

        $dates = RegisterDate::where('register_id', $register_id)
            ->whereNotNull('date')
            ->withCount('bookings')   // gives each row a bookings_count
            ->orderBy('date')
            ->get()
            ->groupBy('date')         // group all rows that share the same date
            ->map(function ($group) {
                $first = $group->first();                 // keep one row as base
                $first->bookings_count = $group->sum('bookings_count');  // sum bookings of that day
                $first->day = Carbon::parse($first->date)
                    ->locale(app()->getLocale())->dayName;
                return $first;
            })
            ->values()
            ->all();
        return $dates;
    }

    public function createDates($school_id, $schoolyear_id, $register_id, array $data): bool
    {


        // --- Normalize days: accept 'days' => ['monday', ...] OR checkbox keys ---
        if (!empty($data['days']) && is_array($data['days'])) {
            $wantedDays = array_values(array_unique(array_map('strtolower', $data['days'])));
        } else {
            $days = [];
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $d) {
                if (!empty($data[$d])) $days[] = $d;
            }
            $wantedDays = array_map('strtolower', $days);
        }

        // --- Normalize supervisors: accept single 'supervisor' or 'supervisors' array ---
        $supervisors = [];
        if (!empty($data['supervisor'])) {
            $supervisors[] = $data['supervisor'];
        }
        if (!empty($data['supervisors']) && is_array($data['supervisors'])) {
            $supervisors = array_merge($supervisors, $data['supervisors']);
        }
        // (Optional) also accept legacy supervisor_1..5
        foreach (range(1, 5) as $i) {
            if (!empty($data["supervisor_$i"])) $supervisors[] = $data["supervisor_$i"];
        }
        $supervisors = array_values(array_unique(array_filter($supervisors, fn($v) => $v !== '' && $v !== null)));

        // --- Required inputs ---
        $date_from         = $data['date_from'];
        $date_until        = $data['date_until'] ?? $date_from;
        $time_from         = $data['time_from'];
        $time_until        = $data['time_until'];
        $min_per_date      = (int)($data['min_per_date'] ?? 0);
        $pause             = (int)($data['pause'] ?? 0);
        $max_registrations = (int)($data['max_registrations'] ?? 1);

        if ($min_per_date <= 0 || empty($wantedDays) || empty($supervisors)) {
            return false;
        }

        $tz        = config('app.timezone') ?? 'Europe/Vienna';
        $startDate = \Carbon\Carbon::parse($date_from, $tz)->startOfDay();
        $endDate   = \Carbon\Carbon::parse($date_until, $tz)->endOfDay();

        // Optional: auto-swap if user gives reversed dates
        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $entries = [];

        foreach (\Carbon\CarbonPeriod::create($startDate, '1 day', $endDate) as $date) {
            if (!in_array(strtolower($date->englishDayOfWeek), $wantedDays, true)) continue;

            $windowStart = $date->copy()->setTimeFromTimeString($time_from);
            $windowEnd   = $date->copy()->setTimeFromTimeString($time_until);
            if ($windowEnd->lessThanOrEqualTo($windowStart)) $windowEnd->addDay(); // overnight

            $cursor = $windowStart->copy();

            while ($cursor->copy()->addMinutes($min_per_date)->lte($windowEnd)) {
                $slotStart = $cursor->copy();
                $slotEnd   = $cursor->copy()->addMinutes($min_per_date);

                foreach ($supervisors as $supervisor) {
                    $entries[] = [
                        // your requested shape includes the base fields + single supervisor
                        'school_id' => $school_id,
                        'schoolyear_id' => $schoolyear_id,
                        'register_id' => $register_id,
                        'supervisor'        => $supervisor,
                        'date'              => $slotStart->toDateString(),
                        'from'              => $slotStart->format('H:i'),
                        'to'                => $slotEnd->format('H:i'),
                        'max_registrations' => $max_registrations,
                    ];
                }

                $cursor->addMinutes($min_per_date + $pause);
            }
        }

        RegisterDate::upsert(
            $entries,
            ['school_id', 'schoolyear_id', 'register_id', 'supervisor', 'date', 'from', 'to'], // unique fields
            [] // don't update anything on duplicate — just skip
        );

        return true;
    }

    public function deleteRegisterDates($school_id, $schoolyear_id, $register_id, $register_dates): bool
    {
        $base = RegisterDate::query()
            ->where('school_id', $school_id)
            ->where('schoolyear_id', $schoolyear_id)
            ->where('register_id', $register_id)
            ->whereIn('id', $register_dates);

        // IDs that are NOT deletable (because they have bookings)
        $blockedIds = (clone $base)
            ->whereHas('bookings')
            ->pluck('id');

        // Delete only dates that have NO bookings
        $deletedCount = (clone $base)
            ->whereDoesntHave('bookings')
            ->delete();

        return true;
    }
}
