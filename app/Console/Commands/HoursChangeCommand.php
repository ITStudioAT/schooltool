<?php

namespace App\Console\Commands;

use App\Models\TeachingCourseDate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class HoursChangeCommand extends Command
{
    private const int TARGET_SCHOOL_ID = 5;

    /** @var array<int, int> */
    private const array HOURS_MAP = [
        4 => 13,
        3 => 12,
        5 => 14,
        6 => 15,
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hours:change';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change teaching course date hours for school_id 5 using the predefined mapping';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updatedRecords = 0;
        $updatedHourValues = 0;

        TeachingCourseDate::query()
            ->whereHas('teachingCourse', function (Builder $query): void {
                $query->where('school_id', self::TARGET_SCHOOL_ID);
            })
            ->orderBy('id')
            ->chunkById(200, function ($courseDates) use (&$updatedRecords, &$updatedHourValues): void {
                foreach ($courseDates as $courseDate) {
                    $hours = $this->normalizeHours($courseDate->hours);
                    if ($hours === []) {
                        continue;
                    }

                    $changedValuesInRecord = 0;
                    $mappedHours = [];

                    foreach ($hours as $hour) {
                        if (! is_numeric($hour)) {
                            $mappedHours[] = $hour;

                            continue;
                        }

                        $hourAsInt = (int) $hour;
                        if (array_key_exists($hourAsInt, self::HOURS_MAP)) {
                            $mappedHours[] = self::HOURS_MAP[$hourAsInt];
                            $changedValuesInRecord++;

                            continue;
                        }

                        $mappedHours[] = $hour;
                    }

                    if ($changedValuesInRecord === 0) {
                        continue;
                    }

                    $courseDate->hours = $mappedHours;
                    $courseDate->save();

                    $updatedRecords++;
                    $updatedHourValues += $changedValuesInRecord;
                }
            });

        $this->info("Updated {$updatedRecords} record(s) and {$updatedHourValues} hour value(s) for school_id ".self::TARGET_SCHOOL_ID.'.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeHours(mixed $hours): array
    {
        if (is_array($hours)) {
            return array_values($hours);
        }

        if (is_numeric($hours)) {
            return [(int) $hours];
        }

        return [];
    }
}
