<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] ?? 'Stundenplan' }}</title>
    @php
        $printOptions = $data['print_options'] ?? [];
        $printSingleWeeks = ($printOptions['single_weeks'] ?? false) === true;
        $printCourseList = ($printOptions['course_list'] ?? true) !== false;
        $printCourseOverview = ($printOptions['course_overview'] ?? true) !== false;
        $semesters = collect($data['semesters'] ?? []);
        $metricSemesters = $printSingleWeeks
            ? $semesters->map(fn (array $semester): array => [
                ...$semester,
                'weeks' => collect($semester['weeks'] ?? [])->take(1)->all(),
            ])
            : $semesters;
        $semesterCount = $printSingleWeeks ? 1 : max(1, $semesters->count());
        $isTwoColumns = $semesterCount > 1;
        $detailSeparatorPattern = '/\s*(?:·|\R)\s*/u';
        $dateRangeDetailPattern = '/^\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?\s*-\s*\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?$/u';
        $isHiddenDateRangeDetail = fn (string $segment): bool => preg_match($dateRangeDetailPattern, trim($segment)) === 1;
        $stripCompactMarkerText = fn (string $text): string => trim(preg_replace('/\s*\(Kompakt(?:unterricht|kurs)?\)\s*/iu', ' ', $text) ?: $text);
        $isCompactCourse = fn (array $course): bool => ! empty($course['is_kompaktunterricht'])
            || ! empty($course['isKompaktunterricht'])
            || ! empty($course['isKompaktunterrichtCourse'])
            || preg_match('/\bKompakt(?:unterricht)?\b/iu', (string) ($course['details'] ?? '')) === 1;
        $courseLearningModeLabel = fn (array $course): string => $isCompactCourse($course)
            ? 'Kompaktunterricht'
            : (! empty($course['is_fu']) ? 'Fernunterricht' : '');
        $parseCourseWeekParityDate = function (?string $date): ?\Carbon\Carbon {
            $date = trim((string) $date);
            if ($date === '') {
                return null;
            }

            foreach (['!Y-m-d', '!d.m.Y', '!d.m.y'] as $format) {
                try {
                    $parsedDate = \Carbon\Carbon::createFromFormat($format, $date);
                } catch (\Throwable) {
                    continue;
                }

                $errors = \Carbon\Carbon::getLastErrors();
                if ($parsedDate && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    return $parsedDate->startOfDay();
                }
            }

            try {
                return \Carbon\Carbon::parse($date)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };
        $courseTwoWeekParitySuffix = function (array $dates) use ($parseCourseWeekParityDate): string {
            $dateValues = collect($dates)
                ->map(fn ($date): string => trim((string) $date))
                ->filter()
                ->values();

            if ($dateValues->isEmpty()) {
                return '';
            }

            $weekNumbers = $dateValues
                ->map(fn (string $date): ?int => $parseCourseWeekParityDate($date)?->isoWeek());

            if ($weekNumbers->count() !== $dateValues->count() || $weekNumbers->contains(null)) {
                return '';
            }

            $parities = $weekNumbers
                ->map(fn (int $weekNumber): int => $weekNumber % 2)
                ->unique()
                ->values();

            if ($parities->count() !== 1) {
                return '';
            }

            return $parities->first() === 0 ? ' A' : ' B';
        };
        $dateWeekParityLabel = fn (\Carbon\Carbon $date): string => $date->isoWeek() % 2 === 0 ? 'A' : 'B';
        $formatDateWithWeekParity = fn (\Carbon\Carbon $date): string => "{$date->format('d.m.')}({$dateWeekParityLabel($date)})";
        $appendTwoWeekParityToSegment = function (string $segment, array $dates) use ($courseTwoWeekParitySuffix): string {
            $suffix = $courseTwoWeekParitySuffix($dates);
            if ($suffix === '') {
                return $segment;
            }

            return preg_replace_callback(
                '/^(\s*2\s*-?\s*w(?:öchig|öching|ochig)?)(\s+[AB])?(\s*:|\s+|$)/iu',
                fn (array $matches): string => empty($matches[2])
                    ? "{$matches[1]}{$suffix}{$matches[3]}"
                    : $matches[0],
                $segment,
                1,
            ) ?? $segment;
        };
        $courseDetailsWithWeekParity = function (array $course) use ($appendTwoWeekParityToSegment): string {
            $details = (string) ($course['details'] ?? '');
            $dates = array_filter(array_map('trim', (array) ($course['dates'] ?? [])));

            if (trim($details) === '' || $dates === []) {
                return $details;
            }

            return collect(preg_split('/\R/u', $details) ?: [])
                ->map(fn (string $line): string => collect(preg_split('/\s*·\s*/u', $line) ?: [])
                    ->map(fn (string $segment): string => $appendTwoWeekParityToSegment($segment, $dates))
                    ->implode(' · '))
                ->implode("\n");
        };

        $semesterMetrics = $metricSemesters->map(function (array $semester) {
            $weeks = collect($semester['weeks'] ?? []);

            return [
                'weekCount' => $weeks->count(),
                'labeledWeekCount' => $weeks
                    ->filter(fn (array $week): bool => trim((string) ($week['label'] ?? '')) !== '')
                    ->count(),
                'hourCount' => $weeks->sum(fn (array $week): int => count($week['hours'] ?? [])),
            ];
        });

        if ($isTwoColumns) {
            $weekCount = max(1, (int) $semesterMetrics->max('weekCount'));
            $labeledWeekCount = (int) $semesterMetrics->max('labeledWeekCount');
            $hourRowCount = max(1, (int) $semesterMetrics->max('hourCount'));
        } else {
            $weekCount = max(1, (int) $semesterMetrics->sum('weekCount'));
            $labeledWeekCount = (int) $semesterMetrics->sum('labeledWeekCount');
            $hourRowCount = max(1, (int) $semesterMetrics->sum('hourCount'));
        }

        $semesterTitleHeight = $isTwoColumns ? 3.5 : ($semesterCount * 3.5);
        $labelHeight = $semesterTitleHeight + ($labeledWeekCount * 2.5) + ($weekCount * 4);
        $pageHeight = 194;
        $headerHeight = 9;
        $availableRowHeight = max(24, $pageHeight - $headerHeight - $labelHeight);
        $baseRowHeight = max(5.2, min(10.5, $availableRowHeight / $hourRowCount));
        $rowHeight = $baseRowHeight;
        $naturalHeight = $headerHeight + $labelHeight + ($hourRowCount * $rowHeight);
        $scale = max(0.45, min(1, $pageHeight / $naturalHeight));
        $contentWidth = 100 / $scale;

        $bodyFontSize = max(6.8, min(8.5, $rowHeight * 1.05));
        $detailFontSize = max(5.5, $bodyFontSize - 1.2);

        $minEffectiveFont = 5.8;
        if ($bodyFontSize * $scale < $minEffectiveFont) {
            $bodyFontSize = min(14, $minEffectiveFont / $scale);
            $detailFontSize = max(5.5, $bodyFontSize - 1.2);
        }

        $weekdayLabels = collect($data['weekdays'] ?? [])->pluck('label')->all();
        $allCourseSlots = collect();
        foreach ($data['semesters'] ?? [] as $sem) {
            $semLabel = $sem['label'] ?? 'Semester';
            $semRange = $sem['date_range'] ?? '';
            foreach ($sem['weeks'] ?? [] as $wk) {
                foreach ($wk['hours'] ?? [] as $hr) {
                    $timeFrom = trim((string) ($hr['from'] ?? ''));
                    $timeUntil = trim((string) ($hr['until'] ?? ''));
                    $timeRange = ($timeFrom !== '' && $timeUntil !== '') ? "{$timeFrom} – {$timeUntil}" : $timeFrom;
                    foreach ($hr['cells'] ?? [] as $cellIdx => $cl) {
                        $cellStatus = $cl['status'] ?? 'empty';
                        foreach ($cl['courses'] ?? [] as $crs) {
                            $crsLabel = trim((string) ($crs['label'] ?? ''));
                            if ($crsLabel === '') {
                                continue;
                            }
                            $isCompact = $isCompactCourse($crs);
                            $allCourseSlots->push([
                                'label' => $crsLabel,
                                'details' => trim($courseDetailsWithWeekParity($crs)),
                                'dates' => array_filter(array_map('trim', (array) ($crs['dates'] ?? []))),
                                'semester' => $semLabel,
                                'semester_range' => $semRange,
                                'weekday' => $weekdayLabels[$cellIdx] ?? '',
                                'weekday_index' => $cellIdx,
                                'hour' => (int) ($hr['hour'] ?? 0),
                                'time' => $timeRange,
                                'status' => $cellStatus,
                                'is_compact' => $isCompact,
                                'is_fu' => ! $isCompact && ! empty($crs['is_fu']),
                                'recurrence_label' => trim((string) ($crs['recurrence_label'] ?? '')),
                                'recurrence_interval' => (int) ($crs['recurrence_interval'] ?? 0),
                            ]);
                        }
                    }
                }
            }
        }
        $slotTimeParts = function (array $slot): array {
            return array_map(
                fn (string $time): string => trim($time),
                array_pad(preg_split('/\s*[–-]\s*/u', (string) ($slot['time'] ?? ''), 2) ?: [], 2, ''),
            );
        };
        $mergeSlotDetails = function (string $leftDetails = '', string $rightDetails = '') use ($detailSeparatorPattern): string {
            return collect([$leftDetails, $rightDetails])
                ->flatMap(fn (string $details): array => preg_split($detailSeparatorPattern, $details) ?: [])
                ->map(fn (string $detail): string => trim($detail))
                ->filter()
                ->unique()
                ->values()
                ->implode(' · ');
        };

        $slotRecurrenceInterval = function (array $slot) use ($detailSeparatorPattern): int {
            $explicitInterval = (int) ($slot['recurrence_interval'] ?? 0);
            if ($explicitInterval > 1) {
                return $explicitInterval;
            }

            $recurrenceSources = collect([
                $slot['recurrence_label'] ?? '',
                $slot['details'] ?? '',
            ]);

            return (int) ($recurrenceSources
                ->flatMap(fn (string $source): array => preg_split($detailSeparatorPattern, $source) ?: [])
                ->map(function (string $segment): int {
                    if (preg_match('/(\d+)\s*-?\s*w/iu', trim($segment), $matches) !== 1) {
                        return 1;
                    }

                    return (int) $matches[1];
                })
                ->filter(fn (int $interval): bool => $interval > 1)
                ->first() ?? 1);
        };

        $slotRecurrenceSignature = function (array $slot) use ($courseTwoWeekParitySuffix, $slotRecurrenceInterval): string {
            $interval = $slotRecurrenceInterval($slot);
            $parity = $interval === 2
                ? trim($courseTwoWeekParitySuffix((array) ($slot['dates'] ?? [])))
                : '';

            return implode('|', [
                $interval,
                $parity,
            ]);
        };
        $courseDirectoryKey = fn (array $slot): string => implode('|', [
            $slot['label'] ?? '',
            $slotRecurrenceSignature($slot),
        ]);

        $allCourseSlots = $allCourseSlots
            ->unique(fn (array $c): string => implode('|', [
                $c['semester'],
                $c['weekday'],
                $c['hour'],
                $c['label'],
                $slotRecurrenceSignature($c),
            ]))
            ->sortBy([['weekday_index', 'asc'], ['hour', 'asc'], ['label', 'asc']])
            ->values();

        $mergeCourseOverviewSlots = function ($slots) use ($slotTimeParts, $mergeSlotDetails, $slotRecurrenceSignature) {
            $mergedSlots = collect();

            $slots
                ->sortBy([['weekday_index', 'asc'], ['hour', 'asc'], ['label', 'asc']])
                ->groupBy(fn (array $slot): string => implode('|', [
                    $slot['semester'] ?? '',
                    $slot['weekday'] ?? '',
                    $slot['label'] ?? '',
                    $slot['status'] ?? '',
                    !empty($slot['is_fu']) ? 'fu' : 'regular',
                    $slotRecurrenceSignature($slot),
                ]))
                ->each(function ($groupedSlots) use ($mergedSlots, $slotTimeParts, $mergeSlotDetails): void {
                    $currentSlot = null;
                    $previousHour = null;
                    $until = '';

                    $pushCurrentSlot = function () use (&$currentSlot, &$previousHour, &$until, $mergedSlots): void {
                        if ($currentSlot === null) {
                            return;
                        }

                        $startHour = (int) ($currentSlot['hour'] ?? 0);
                        $endHour = (int) ($previousHour ?? $startHour);
                        $currentSlot['hour_label'] = $startHour === $endHour
                            ? "{$startHour}."
                            : "{$startHour}.-{$endHour}.";

                        [$from] = array_pad(preg_split('/\s*[–-]\s*/u', (string) ($currentSlot['time'] ?? ''), 2) ?: [], 2, '');
                        $from = trim((string) $from);
                        $currentSlot['time'] = ($from !== '' && $until !== '')
                            ? "{$from} – {$until}"
                            : trim(collect([$from, $until])->filter()->implode(' – '));

                        $mergedSlots->push($currentSlot);
                    };

                    foreach ($groupedSlots->values() as $slot) {
                        $hour = (int) ($slot['hour'] ?? 0);
                        [, $slotUntil] = $slotTimeParts($slot);

                        if ($currentSlot === null) {
                            $currentSlot = $slot;
                            $previousHour = $hour;
                            $until = $slotUntil;

                            continue;
                        }

                        if ($hour === (int) $previousHour + 1) {
                            $currentSlot['details'] = $mergeSlotDetails(
                                (string) ($currentSlot['details'] ?? ''),
                                (string) ($slot['details'] ?? ''),
                            );
                            $previousHour = $hour;
                            $until = $slotUntil !== '' ? $slotUntil : $until;

                            continue;
                        }

                        $pushCurrentSlot();
                        $currentSlot = $slot;
                        $previousHour = $hour;
                        $until = $slotUntil;
                    }

                    $pushCurrentSlot();
                });

            return $mergedSlots
                ->sortBy([['weekday_index', 'asc'], ['hour', 'asc'], ['label', 'asc']])
                ->values();
        };
        $courseSemesters = $allCourseSlots->groupBy('semester');
        $courseOverviewSemesters = $courseSemesters
            ->map(fn ($slots) => $mergeCourseOverviewSlots($slots))
            ->filter(fn ($slots): bool => $slots->isNotEmpty());
        $directorySlotSummary = function ($slots): string {
            return $slots
                ->groupBy('weekday')
                ->map(function ($weekdaySlots, string $weekday): string {
                    $hours = $weekdaySlots
                        ->filter(fn (array $slot): bool => (int) ($slot['hour'] ?? 0) > 0)
                        ->sortBy('hour')
                        ->unique('hour')
                        ->values();

                    $ranges = collect();
                    $rangeStart = null;
                    $previousHour = null;
                    $rangeStartTime = '';
                    $rangeEndTime = '';

                    $pushRange = function () use (&$ranges, &$rangeStart, &$previousHour, &$rangeStartTime, &$rangeEndTime): void {
                        if ($rangeStart === null) {
                            return;
                        }

                        $hourRange = $rangeStart === $previousHour ? "{$rangeStart}." : "{$rangeStart}.-{$previousHour}.";
                        $ranges->push(collect([$hourRange, $rangeStartTime && $rangeEndTime ? "{$rangeStartTime} - {$rangeEndTime}" : ''])
                            ->filter()
                            ->implode(' '));
                    };

                    foreach ($hours as $slot) {
                        $hour = (int) ($slot['hour'] ?? 0);
                        [$from, $until] = array_pad(preg_split('/\s*[–-]\s*/u', (string) ($slot['time'] ?? ''), 2) ?: [], 2, '');

                        if ($rangeStart === null) {
                            $rangeStart = $hour;
                            $previousHour = $hour;
                            $rangeStartTime = trim((string) $from);
                            $rangeEndTime = trim((string) $until);

                            continue;
                        }

                        if ($hour === $previousHour + 1) {
                            $previousHour = $hour;
                            $rangeEndTime = trim((string) $until) ?: $rangeEndTime;

                            continue;
                        }

                        $pushRange();
                        $rangeStart = $hour;
                        $previousHour = $hour;
                        $rangeStartTime = trim((string) $from);
                        $rangeEndTime = trim((string) $until);
                    }

                    $pushRange();

                    return $ranges
                        ->map(fn (string $range): string => trim("{$weekday} {$range}"))
                        ->implode(', ');
                })
                ->filter()
                ->values()
                ->implode(', ');
        };
        $directoryDetails = function ($slots) use ($detailSeparatorPattern, $isHiddenDateRangeDetail): string {
            $hasCompactCourse = $slots->contains('is_compact', true);
            $segments = $slots
                ->pluck('details')
                ->filter()
                ->flatMap(fn (string $details) => preg_split($detailSeparatorPattern, $details) ?: [])
                ->map(fn (string $segment): string => trim($segment))
                ->filter()
                ->unique()
                ->values();

            $remainingSegments = $segments
                ->reject(fn (string $segment): bool => preg_match('/^(\d+)\s*-?\s*w(?:öchig|öching|ochig)?(?:\s+[AB])?$/iu', $segment) === 1)
                ->reject(fn (string $segment): bool => preg_match('/^\d{1,2}\.\d{1,2}\.(\d{2,4})?$/u', $segment) === 1)
                ->reject($isHiddenDateRangeDetail)
                ->reject(fn (string $segment): bool => $hasCompactCourse
                    && preg_match('/\bKompakt(?:unterricht|kurs)?\b/iu', $segment) === 1
                    && preg_match('/\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?\s*-\s*\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?/u', $segment) === 1)
                ->values();

            return collect($remainingSegments)
                ->filter()
                ->unique()
                ->implode(' ');
        };

        $directoryHints = function ($slots) use ($detailSeparatorPattern): array {
            $recurrenceHints = $slots
                ->pluck('details')
                ->filter()
                ->flatMap(fn (string $details) => preg_split($detailSeparatorPattern, $details) ?: [])
                ->map(fn (string $segment): string => trim($segment))
                ->filter()
                ->map(function (string $segment): ?string {
                    if (preg_match('/^(\d+)\s*-?\s*w(?:öchig|öching|ochig)?(?:\s+([AB]))?$/iu', $segment, $matches) !== 1) {
                        return null;
                    }

                    $suffix = isset($matches[2]) && $matches[2] !== '' ? " {$matches[2]}" : '';

                    return "{$matches[1]}-wöchentlich{$suffix}";
                })
                ->filter()
                ->unique()
                ->sort()
                ->values();

            return collect([
                $slots->contains('is_compact', true) ? 'Kompaktkurs' : null,
                ! $slots->contains('is_compact', true) && $slots->contains('is_fu', true) ? 'Fernunterricht' : null,
                ...$recurrenceHints,
            ])
                ->filter()
                ->unique()
                ->values()
                ->all();
        };

        $formatDetailsHtml = function (
            ?string $details,
            bool $hideCompactDateDetails = false,
            bool $hideCourseHintDetails = false,
            bool $stripCompactMarkers = false,
        ) use ($isHiddenDateRangeDetail, $stripCompactMarkerText): string {
            $formatSegment = function (string $segment) use ($hideCompactDateDetails, $hideCourseHintDetails, $isHiddenDateRangeDetail, $stripCompactMarkerText, $stripCompactMarkers): string {
                if ($stripCompactMarkers) {
                    $segment = $stripCompactMarkerText($segment);
                }

                if ($isHiddenDateRangeDetail($segment)) {
                    return '';
                }

                if ($hideCompactDateDetails
                    && preg_match('/\bKompakt(?:unterricht|kurs)?\b/iu', $segment) === 1
                    && preg_match('/\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?\s*-\s*\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?/u', $segment) === 1) {
                    return '';
                }

                if ($hideCourseHintDetails
                    && preg_match('/\bKompakt(?:unterricht|kurs)?\b/iu', $segment) === 1
                    && preg_match('/\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?\s*-\s*\d{1,2}\.\d{1,2}\.?(?:\d{2,4})?/u', $segment) === 1) {
                    return e($stripCompactMarkerText($segment));
                }

                if ($hideCourseHintDetails && preg_match('/^(\d+\s*-?\s*w[^\s]*(?:\s+[AB])?)([:\s].*)?$/iu', $segment, $matches) === 1) {
                    $remainingText = preg_replace('/^\s*:\s*/u', '', (string) ($matches[2] ?? '')) ?: '';

                    return e(trim($remainingText));
                }

                if ($hideCourseHintDetails && preg_match('/^(Fernunterricht|Kompakt(?:unterricht|kurs)?)(\s+.*)?$/iu', $segment, $matches) === 1) {
                    return e(trim((string) ($matches[2] ?? '')));
                }

                if (preg_match('/^(\d+\s*-?\s*w(?:öchig|öching|ochig)?(?:\s+[AB])?)([:\s].*)?$/iu', $segment, $matches) === 1) {
                    $recurrence = e($matches[1]);
                    $remainingText = e($matches[2] ?? '');

                    return "<span class=\"recurrence-detail\">{$recurrence}</span>{$remainingText}";
                }

                return e($segment);
            };

            return collect(preg_split('/\R/u', (string) $details) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->map(fn (string $line): string => collect(preg_split('/\s*·\s*/u', $line) ?: [])
                    ->map(fn (string $segment): string => trim($segment))
                    ->filter()
                    ->map($formatSegment)
                    ->filter()
                    ->implode(' · '))
                ->filter()
                ->map(fn (string $line): string => "<span class=\"course-detail-line\">{$line}</span>")
                ->implode('');
        };
        $courseTitleLabel = fn (array $course): string => preg_replace(
            '/^(\p{L}+)\s+(\d)/u',
            '$1$2',
            trim((string) ($course['label'] ?? ''))
        ) ?: trim((string) ($course['label'] ?? ''));

        $courseTitleCode = function (array $course) use ($courseTitleLabel): string {
            $label = $courseTitleLabel($course);

            if (preg_match('/^(\p{L}+\d+(?:\.\d+)?)/u', $label, $matches) === 1) {
                return $matches[1];
            }

            return preg_replace('/\s+/u', '', $label) ?: '';
        };

        $courseTitleSourceLine = function (array $course) use ($courseTitleCode, $isHiddenDateRangeDetail): string {
            $titleCode = $courseTitleCode($course);
            if ($titleCode === '') {
                return '';
            }

            $escapedTitleCode = preg_quote($titleCode, '/');

            return collect(preg_split('/\R/u', (string) ($course['details'] ?? '')) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->first(fn (string $line): bool => ! $isHiddenDateRangeDetail($line)
                    && preg_match("/^{$escapedTitleCode}\s*[-\s]+.+$/iu", $line) === 1
                ) ?? '';
        };

        $courseTitleContext = function (array $course) use ($courseTitleCode, $courseTitleSourceLine): string {
            $titleCode = $courseTitleCode($course);
            $sourceLine = $courseTitleSourceLine($course);
            if ($titleCode === '' || $sourceLine === '') {
                return '';
            }

            $escapedTitleCode = preg_quote($titleCode, '/');
            $context = preg_replace("/^{$escapedTitleCode}\s*[-\s]*/iu", '', $sourceLine) ?: '';

            return trim($context) !== trim($sourceLine) ? trim($context) : '';
        };

        $courseDetailsForRendering = function (array $course) use ($courseDetailsWithWeekParity, $courseTitleSourceLine): string {
            $sourceLine = $courseTitleSourceLine($course);

            return collect(preg_split('/\R/u', $courseDetailsWithWeekParity($course)) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->reject(fn (string $line): bool => $sourceLine !== '' && $line === $sourceLine)
                ->implode("\n");
        };

        $parseDirectoryDate = function (string $date) use ($formatDateWithWeekParity): array {
            $date = trim($date);
            $formats = [
                '!Y-m-d' => 'Y-m-d',
                '!d.m.Y' => 'Y-m-d',
                '!d.m.y' => 'Y-m-d',
                '!d.m.' => 'm-d',
                '!d.m' => 'm-d',
            ];

            foreach ($formats as $format => $sortFormat) {
                try {
                    $parsedDate = \Carbon\Carbon::createFromFormat($format, $date);
                } catch (\Throwable) {
                    continue;
                }

                $errors = \Carbon\Carbon::getLastErrors();

                if (! $parsedDate || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                    continue;
                }

                return [
                    'key' => $parsedDate->format($sortFormat),
                    'label' => $sortFormat === 'Y-m-d'
                        ? $formatDateWithWeekParity($parsedDate)
                        : $parsedDate->format('d.m.'),
                ];
            }

            try {
                $parsedDate = \Carbon\Carbon::parse($date);

                return [
                    'key' => $parsedDate->format('Y-m-d'),
                    'label' => $formatDateWithWeekParity($parsedDate),
                ];
            } catch (\Throwable) {
                return [
                    'key' => "z-{$date}",
                    'label' => $date,
                ];
            }
        };

        $formatCourseDateGroups = fn ($slots) => $slots
            ->map(function (array $slot) use ($parseDirectoryDate): ?array {
                $dates = collect($slot['dates'] ?? [])
                    ->map(fn (string $date): string => trim($date))
                    ->filter()
                    ->map(fn (string $date): array => $parseDirectoryDate($date))
                    ->unique('key')
                    ->sortBy('key')
                    ->values();

                if ($dates->isEmpty()) {
                    return null;
                }

                return [
                    'dates' => $dates->all(),
                    'weekday' => trim((string) ($slot['weekday'] ?? '')),
                    'weekday_index' => (int) ($slot['weekday_index'] ?? 0),
                ];
            })
            ->filter()
            ->groupBy(fn (array $group): string => "{$group['weekday_index']}|{$group['weekday']}")
            ->map(function ($groups): array {
                $firstGroup = $groups->first();

                return [
                    'dates' => $groups
                        ->flatMap(fn (array $group): array => $group['dates'])
                        ->unique('key')
                        ->sortBy('key')
                        ->pluck('label')
                        ->values()
                        ->all(),
                    'weekday' => $firstGroup['weekday'] ?? '',
                    'weekday_index' => (int) ($firstGroup['weekday_index'] ?? 0),
                ];
            })
            ->sortBy([['weekday_index', 'asc'], ['weekday', 'asc']])
            ->values()
            ->all();

        $courseDirectory = $allCourseSlots
            ->groupBy($courseDirectoryKey)
            ->map(function ($slots, string $key) use ($directorySlotSummary, $directoryDetails, $directoryHints, $formatCourseDateGroups) {
                $dateGroups = $formatCourseDateGroups($slots);

                return [
                    'key' => $key,
                    'label' => $slots->first()['label'] ?? '',
                    'details' => $directoryDetails($slots),
                    'hints' => $directoryHints($slots),
                    'slots' => $directorySlotSummary($slots),
                    'date_groups' => $dateGroups,
                    'dates' => collect($dateGroups)
                        ->flatMap(fn (array $dateGroup): array => $dateGroup['dates'])
                        ->unique()
                        ->values()
                        ->all(),
                    'status' => $slots->contains('status', 'conflict') ? 'conflict'
                        : ($slots->contains('status', 'warning') ? 'warning'
                        : ($slots->contains('status', 'related') ? 'related' : 'filled')),
                    'is_compact' => $slots->contains('is_compact', true),
                    'is_fu' => ! $slots->contains('is_compact', true) && $slots->contains('is_fu', true),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $courseDirectoryHintsByKey = $courseDirectory->mapWithKeys(fn (array $entry): array => [
            $entry['key'] => $entry['hints'],
        ]);

        $courseRecurrenceInterval = $slotRecurrenceInterval;

        $parseTimetableDate = function (?string $date, ?int $fallbackYear = null): ?\Carbon\Carbon {
            $date = trim((string) $date);
            if ($date === '') {
                return null;
            }

            $formats = ['!Y-m-d', '!d.m.Y', '!d.m.y'];
            foreach ($formats as $format) {
                try {
                    $parsedDate = \Carbon\Carbon::createFromFormat($format, $date);
                } catch (\Throwable) {
                    continue;
                }

                $errors = \Carbon\Carbon::getLastErrors();
                if ($parsedDate && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                    return $parsedDate->startOfDay();
                }
            }

            if ($fallbackYear !== null && preg_match('/^\d{1,2}\.\d{1,2}\.?$/u', $date) === 1) {
                $dateWithYear = rtrim($date, '.').".{$fallbackYear}";

                try {
                    $parsedDate = \Carbon\Carbon::createFromFormat('!d.m.Y', $dateWithYear);

                    return $parsedDate ? $parsedDate->startOfDay() : null;
                } catch (\Throwable) {
                    return null;
                }
            }

            try {
                return \Carbon\Carbon::parse($date)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        $semesterStartDate = function (array $semester) use ($parseTimetableDate): ?\Carbon\Carbon {
            if (preg_match('/^\s*([0-9]{1,2}\.[0-9]{1,2}\.?(?:[0-9]{2,4})?)/u', (string) ($semester['date_range'] ?? ''), $matches) === 1) {
                $dateRangeStart = $parseTimetableDate($matches[1]);
                if ($dateRangeStart) {
                    return $dateRangeStart;
                }
            }

            return collect($semester['weeks'] ?? [])
                ->flatMap(fn (array $week): array => $week['hours'] ?? [])
                ->flatMap(fn (array $hour): array => $hour['cells'] ?? [])
                ->flatMap(fn (array $cell): array => $cell['courses'] ?? [])
                ->flatMap(fn (array $course): array => $course['dates'] ?? [])
                ->map(fn (string $date): ?\Carbon\Carbon => $parseTimetableDate($date))
                ->filter()
                ->sortBy(fn (\Carbon\Carbon $date): int => $date->getTimestamp())
                ->first();
        };

        $courseRecurrenceWeek = function (array $course, array $semester) use ($courseRecurrenceInterval, $parseTimetableDate, $semesterStartDate): int {
            $interval = $courseRecurrenceInterval($course);
            if ($interval <= 1) {
                return 1;
            }

            $semesterStart = $semesterStartDate($semester);
            $fallbackYear = $semesterStart?->year;
            $firstDate = collect($course['dates'] ?? [])
                ->map(fn (string $date): ?\Carbon\Carbon => $parseTimetableDate($date, $fallbackYear))
                ->filter()
                ->sortBy(fn (\Carbon\Carbon $date): int => $date->getTimestamp())
                ->first();

            if (! $firstDate || ! $semesterStart) {
                return 1;
            }

            $dayDifference = (int) floor(($firstDate->getTimestamp() - $semesterStart->getTimestamp()) / 86400);
            $weekOffset = (int) floor($dayDifference / 7);
            $normalizedOffset = (($weekOffset % $interval) + $interval) % $interval;

            return $normalizedOffset + 1;
        };

        $courseMatchesRecurrenceWeek = function (array $course, array $semester, int $targetWeek) use ($courseRecurrenceInterval, $courseRecurrenceWeek): bool {
            $interval = $courseRecurrenceInterval($course);
            if ($interval <= 1) {
                return true;
            }

            $startWeek = $courseRecurrenceWeek($course, $semester);

            return ((($targetWeek - $startWeek) % $interval) + $interval) % $interval === 0;
        };

        $normalizedMarkerText = fn (?string $text): string => preg_replace('/\s+/u', '', mb_strtolower((string) $text)) ?: '';
        $markerMatchesCourses = function (array $marker, array $courses) use ($courseTitleCode, $normalizedMarkerText): bool {
            $markerTexts = collect([
                $marker['label'] ?? '',
                $marker['title'] ?? '',
            ])
                ->map($normalizedMarkerText)
                ->filter();

            if ($markerTexts->isEmpty()) {
                return false;
            }

            return collect($courses)->contains(function (array $course) use ($courseTitleCode, $markerTexts, $normalizedMarkerText): bool {
                $courseTexts = collect([
                    $course['label'] ?? '',
                    $courseTitleCode($course),
                ])
                    ->map($normalizedMarkerText)
                    ->filter();

                return $courseTexts->contains(function (string $courseText) use ($markerTexts): bool {
                    return $markerTexts->contains(fn (string $markerText): bool => str_contains($courseText, $markerText) || str_contains($markerText, $courseText));
                });
            });
        };

        $filteredCellStatus = function (string $status, array $courses, array $markers): string {
            if (count($courses) === 0 && count($markers) === 0) {
                return 'empty';
            }

            if (count($courses) > 1 && in_array($status, ['warning', 'conflict', 'related'], true)) {
                return $status;
            }

            if ($status === 'related' && count($markers) > 0) {
                return 'related';
            }

            return 'filled';
        };

        $filterTimetableDataForRecurrenceWeek = function (array $sourceData, int $targetWeek) use ($courseMatchesRecurrenceWeek, $filteredCellStatus, $markerMatchesCourses): array {
            $filteredData = $sourceData;
            $filteredData['title'] = trim((string) ($sourceData['title'] ?? 'Stundenplan'))." - Woche {$targetWeek}";

            foreach ($sourceData['semesters'] ?? [] as $semesterIndex => $semester) {
                foreach ($semester['weeks'] ?? [] as $weekIndex => $week) {
                    foreach ($week['hours'] ?? [] as $hourIndex => $hour) {
                        foreach ($hour['cells'] ?? [] as $cellIndex => $cell) {
                            $courses = collect($cell['courses'] ?? [])
                                ->filter(fn (array $course): bool => $courseMatchesRecurrenceWeek($course, $semester, $targetWeek))
                                ->values()
                                ->all();
                            $markers = collect($cell['markers'] ?? [])
                                ->filter(fn (array $marker): bool => $markerMatchesCourses($marker, $courses))
                                ->values()
                                ->all();

                            $filteredData['semesters'][$semesterIndex]['weeks'][$weekIndex]['hours'][$hourIndex]['cells'][$cellIndex] = [
                                ...$cell,
                                'status' => $filteredCellStatus((string) ($cell['status'] ?? 'empty'), $courses, $markers),
                                'courses' => $courses,
                                'markers' => $markers,
                            ];
                        }
                    }
                }
            }

            return $filteredData;
        };

        $maxRecurrenceInterval = (int) $semesters
            ->flatMap(fn (array $semester): array => $semester['weeks'] ?? [])
            ->flatMap(fn (array $week): array => $week['hours'] ?? [])
            ->flatMap(fn (array $hour): array => $hour['cells'] ?? [])
            ->flatMap(fn (array $cell): array => $cell['courses'] ?? [])
            ->map($courseRecurrenceInterval)
            ->filter(fn (int $interval): bool => $interval > 1)
            ->max();

        $splitTimetableDataIntoWeekPages = function (array $sourceData, bool $isAdditional): \Illuminate\Support\Collection {
            return collect($sourceData['semesters'] ?? [])
                ->flatMap(function (array $semester) use ($sourceData, $isAdditional): \Illuminate\Support\Collection {
                    return collect($semester['weeks'] ?? [])
                        ->map(fn (array $week): array => [
                            'data' => [
                                ...$sourceData,
                                'semesters' => [[
                                    ...$semester,
                                    'weeks' => [$week],
                                ]],
                            ],
                            'is_additional' => $isAdditional,
                        ]);
                })
                ->values();
        };

        $timetablePages = $printSingleWeeks
            ? $splitTimetableDataIntoWeekPages($data, false)
            : collect([[
                'data' => $data,
                'is_additional' => false,
            ]]);

        if ($printSingleWeeks && $maxRecurrenceInterval > 1) {
            $timetablePages = $timetablePages->merge(
                collect(range(1, $maxRecurrenceInterval))
                    ->flatMap(function (int $week) use ($data, $filterTimetableDataForRecurrenceWeek, $printSingleWeeks, $splitTimetableDataIntoWeekPages): \Illuminate\Support\Collection {
                        $filteredData = $filterTimetableDataForRecurrenceWeek($data, $week);

                        return $printSingleWeeks
                            ? $splitTimetableDataIntoWeekPages($filteredData, true)
                            : collect([[
                                'data' => $filteredData,
                                'is_additional' => true,
                            ]]);
                    })
            );
        }
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: var(--pdf-body-font-size);
            line-height: 1.18;
        }

        .pdf-page {
            position: relative;
            width: 257mm;
            height: 194mm;
            overflow: hidden;
            page-break-after: avoid;
            break-after: avoid;
        }

        .pdf-page--additional {
            page-break-before: always;
            break-before: page;
        }

        .pdf-content {
            width: var(--pdf-content-width);
            transform: scale(var(--pdf-scale));
            transform-origin: top left;
        }

        .header {
            margin-bottom: 1.5mm;
        }

        .title {
            margin: 0;
            color: #172554;
            font-size: 11pt;
            line-height: 1.1;
        }

        .meta {
            max-width: 100%;
            margin-top: 0.5mm;
            color: #475569;
            font-size: 8.5pt;
            line-height: 1.2;
            text-align: left;
        }

        .semesters {
            display: grid;
            grid-template-columns: repeat(var(--pdf-semester-columns), minmax(0, 1fr));
            gap: 2.5mm;
            break-inside: auto;
            page-break-inside: auto;
        }

        .semester {
            break-inside: auto;
            page-break-inside: auto;
            margin-bottom: 0;
        }

        .semester-title {
            margin-bottom: 0.5mm;
            color: #1e3a8a;
            font-size: 7pt;
            font-weight: 700;
            line-height: 1.15;
        }

        .week-title {
            margin: 1mm 0 0.5mm;
            color: #334155;
            font-size: 6pt;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            break-inside: auto;
            page-break-inside: auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }

        th {
            height: 3.5mm;
            padding: 0.5mm 0.6mm;
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 7.5pt;
            font-weight: 700;
            text-align: center;
            line-height: 1.1;
        }

        td {
            height: auto;
            padding: 0.55mm 0.6mm 1.15mm;
            background: #f8fafc;
            font-size: var(--pdf-body-font-size);
            line-height: 1.15;
            overflow: visible;
        }

        .time-cell {
            width: 10mm;
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: 700;
            text-align: center;
        }

        .time-range {
            margin-top: 0.2mm;
            color: #475569;
            font-size: var(--pdf-detail-font-size);
            font-weight: 400;
            line-height: 1.05;
        }

        .cell-filled {
            background: #bbf7d0;
        }

        .cell-warning {
            background: #fed7aa;
            color: #7c2d12;
        }

        .cell-conflict {
            background: #fecaca;
            color: #7f1d1d;
        }

        .cell-related {
            background: #fed7aa;
            color: #7c2d12;
        }

        .course + .course {
            margin-top: 0.3mm;
        }

        .course--compact + .course--compact {
            margin-top: 1.2mm;
        }

        .cell-content {
            position: relative;
            height: auto;
            overflow: visible;
        }

        .course-label {
            display: table;
            width: 100%;
            table-layout: fixed;
            font-weight: 700;
            line-height: 1.15;
            word-break: break-word;
        }

        .course-label-main,
        .course-label-context {
            display: table-cell;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: top;
            white-space: nowrap;
        }

        .course-label-main {
            font-size: 1.08em;
            font-weight: 700;
        }

        .course-label-context {
            color: #334155;
            font-size: 0.88em;
            font-weight: 700;
            padding-left: 0.8mm;
            text-align: right;
            width: 48%;
        }

        .course--compact .course-label {
            line-height: 1.0;
            word-break: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .course-details,
        .course-more {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .course-details {
            margin-top: 0.2mm;
            color: #475569;
            font-size: var(--pdf-detail-font-size);
            line-height: 1.1;
        }

        .course-detail-line {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .course-more {
            white-space: nowrap;
        }

        .recurrence-detail {
            color: #1d4ed8;
            font-weight: 700;
        }

        .course-fu {
            display: block;
            margin-top: 0.4mm;
            color: #1d4ed8;
            font-size: var(--pdf-detail-font-size);
            font-weight: 700;
            line-height: 1.1;
            white-space: nowrap;
        }

        .course-more {
            margin-top: 0.3mm;
            color: #7c2d12;
            font-size: var(--pdf-detail-font-size);
            font-weight: 700;
            line-height: 1.05;
        }

        .markers {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            gap: 0.4mm;
        }

        .marker {
            display: inline-block;
            padding: 0.15mm 0.5mm;
            border-radius: 1mm;
            background: #e0e7ff;
            color: #3730a3;
            font-weight: 700;
            font-size: var(--pdf-detail-font-size);
            line-height: 1.1;
            white-space: nowrap;
        }

        .pdf-page-courses {
            page-break-before: always;
            break-before: page;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        .courses-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 4mm;
            padding-bottom: 2mm;
            border-bottom: 0.6mm solid #1e3a8a;
        }

        .courses-title {
            margin: 0;
            color: #172554;
            font-size: 14pt;
            font-weight: 700;
            line-height: 1.2;
        }

        .courses-meta {
            color: #475569;
            font-size: 8pt;
            line-height: 1.3;
            text-align: right;
        }

        .courses-semester-title {
            margin: 3mm 0 1.5mm;
            padding: 1mm 2mm;
            background: #1e3a8a;
            color: #fff;
            font-size: 8.5pt;
            font-weight: 700;
            line-height: 1.3;
            border-radius: 1mm;
        }

        .courses-semester-title span {
            font-weight: 400;
            opacity: 0.85;
        }

        .courses-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8pt;
            line-height: 1.35;
            margin-bottom: 2mm;
        }

        .courses-table th {
            height: auto;
            padding: 1.2mm 2mm;
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 7.5pt;
            font-weight: 700;
            text-align: left;
            line-height: 1.3;
            border: none;
            border-bottom: 0.4mm solid #93c5fd;
        }

        .courses-table td {
            height: auto;
            padding: 1mm 2mm;
            border: none;
            border-bottom: 0.2mm solid #e2e8f0;
            vertical-align: middle;
            font-size: 8pt;
            background: transparent;
            overflow: visible;
        }

        .courses-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .courses-table .col-weekday { width: 8%; }
        .courses-table .col-hour { width: 6%; text-align: center; }
        .courses-table .col-time { width: 14%; }
        .courses-table .col-label { width: 24%; }
        .courses-table .col-hints { width: 14%; }
        .courses-table .col-details { width: 34%; }

        .courses-table .col-directory-label { width: 14%; }
        .courses-table .col-directory-hints { width: 12%; }
        .courses-table .col-directory-details { width: 20%; }
        .courses-table .col-directory-slots { width: 54%; }

        .directory-dates-row td {
            border-top: none;
            padding-top: 0;
        }

        .directory-dates-cell {
            color: #64748b;
            font-size: 7pt;
            line-height: 1.4;
        }

        .directory-date-group {
            display: block;
        }

        .directory-date-weekday {
            color: #334155;
            display: inline-block;
            font-weight: 700;
            margin-right: 1.2mm;
        }

        .directory-date {
            display: inline-block;
            margin-right: 3mm;
        }

        .courses-table .cell-weekday {
            font-weight: 400;
            color: #0f172a;
        }

        .courses-table .cell-hour {
            text-align: center;
            font-weight: 700;
        }

        .courses-table .cell-time {
            color: #64748b;
        }

        .courses-table .cell-label {
            font-weight: 700;
        }

        .course-hint {
            color: #1d4ed8;
            font-weight: 700;
            white-space: nowrap;
        }

        .course-hint + .course-hint::before {
            color: #64748b;
            content: " · ";
            font-weight: 400;
        }

        .courses-table .cell-details {
            color: #64748b;
        }

        .fu-badge {
            display: inline-block;
            padding: 0.2mm 0.8mm;
            margin-left: 1.4mm;
            background: #bfdbfe;
            color: #1e40af;
            font-size: 0.78em;
            font-weight: 700;
            line-height: 1.25;
            text-align: center;
            border: 0.15mm solid #60a5fa;
            border-radius: 0.8mm;
            vertical-align: baseline;
        }

        .student-course-badge {
            display: inline-block;
            padding: 0.15mm 0.8mm;
            margin-left: 0.5mm;
            border-radius: 2mm;
            font-size: 0.68em;
            font-weight: 700;
            line-height: 1.2;
            vertical-align: middle;
            white-space: nowrap;
        }

        .student-course-badge--missing {
            background: rgba(251, 146, 60, 0.24);
            color: #9a3412;
        }

        .student-course-badge--additional {
            background: rgba(14, 165, 233, 0.22);
            color: #0369a1;
        }
    </style>
</head>
<body
    style="
        --pdf-scale: {{ number_format($scale, 3, '.', '') }};
        --pdf-content-width: {{ number_format($contentWidth, 2, '.', '') }}%;
        --pdf-row-height: {{ number_format($rowHeight, 2, '.', '') }}mm;
        --pdf-body-font-size: {{ number_format($bodyFontSize, 2, '.', '') }}pt;
        --pdf-detail-font-size: {{ number_format($detailFontSize, 2, '.', '') }}pt;
        --pdf-semester-columns: {{ $semesterCount > 1 ? 2 : 1 }};
    "
>
    @foreach($timetablePages as $timetablePage)
        @php
            $pageData = $timetablePage['data'];
            $pageClass = $timetablePage['is_additional'] ? 'pdf-page pdf-page--additional' : 'pdf-page';
        @endphp
    <main class="{{ $pageClass }}">
        <div class="pdf-content">
            <div class="header">
                <h1 class="title">{{ $pageData['title'] ?? 'Stundenplan' }}</h1>
                <div class="meta">
                    @foreach(array_filter([$pageData['schoolyear'] ?? null, $pageData['student'] ?? null, $pageData['subtitle'] ?? null, $pageData['generated_at'] ?? null]) as $meta)
                        <span>{{ $meta }}</span>@if(! $loop->last)<span> &middot; </span>@endif
                    @endforeach
                </div>
            </div>

            <div class="semesters">
                @foreach($pageData['semesters'] ?? [] as $semester)
                    <section class="semester">
                        <div class="semester-title">
                            {{ $semester['label'] ?? 'Semester' }}
                            @if(! empty($semester['date_range']))
                                <span>({{ $semester['date_range'] }})</span>
                            @endif
                        </div>

                        @foreach($semester['weeks'] ?? [] as $week)
                            @if(! empty($week['label']))
                                <div class="week-title">{{ $week['label'] }}</div>
                            @endif

                            <table>
                                <thead>
                                    <tr>
                                        <th class="time-cell">Std.</th>
                                        @foreach($pageData['weekdays'] ?? [] as $weekday)
                                            <th>{{ $weekday['label'] ?? '' }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($week['hours'] ?? [] as $hour)
                                        <tr>
                                            <td class="time-cell">
                                                {{ $hour['hour'] ?? '' }}.
                                                @if(! empty($hour['from']) || ! empty($hour['until']))
                                                    <div class="time-range">
                                                        {{ $hour['from'] ?? '' }}<br>
                                                        {{ $hour['until'] ?? '' }}
                                                    </div>
                                                @endif
                                            </td>
                                            @foreach($hour['cells'] ?? [] as $cell)
                                                @php
                                                    $cellStatus = $cell['status'] ?? 'empty';
                                                    $cellCourses = collect($cell['courses'] ?? []);
                                                    $cellMarkers = collect($cell['markers'] ?? []);

                                                    if ($cellStatus === 'warning' && $cellMarkers->isNotEmpty() && $cellCourses->count() <= 1) {
                                                        $cellStatus = 'filled';
                                                    }
                                                @endphp
                                                <td class="cell-{{ $cellStatus }}">
                                                    <div class="cell-content">
                                                        @php
                                                            $courses = $cellCourses;
                                                            $hasDenseCourses = $courses->count() > 1;
                                                            $shownCourses = $courses;
                                                        @endphp

                                                        @foreach($shownCourses as $course)
                                                            <div class="course @if($hasDenseCourses) course--compact @endif">
                                                                @php
                                                                    $titleLabel = $courseTitleLabel($course);
                                                                    $courseDetails = $courseDetailsForRendering($course);
                                                                    $learningModeLabel = $courseLearningModeLabel($course);
                                                                    $titleContext = $courseTitleContext($course);
                                                                    if ($learningModeLabel === 'Kompaktunterricht') {
                                                                        $titleContext = $stripCompactMarkerText($titleContext);
                                                                    }
                                                                @endphp
                                                                <div class="course-label">
                                                                    <span class="course-label-main">
                                                                        {{ $titleLabel }}
                                                                        @if(! empty($course['student_course_badge']) && in_array($course['student_course_type'] ?? '', ['missing', 'additional'], true))
                                                                            <span class="student-course-badge student-course-badge--{{ $course['student_course_type'] }}">{{ $course['student_course_badge'] }}</span>
                                                                        @endif
                                                                    </span>
                                                                    @if($titleContext !== '')
                                                                        <span class="course-label-context">{{ $titleContext }}</span>
                                                                    @endif
                                                                </div>
                                                                @if($courseDetails !== '')
                                                                    <div class="course-details">{!! $formatDetailsHtml($courseDetails, false, $timetablePage['is_additional'], $learningModeLabel === 'Kompaktunterricht') !!}</div>
                                                                @endif
                                                                @if($learningModeLabel !== '')
                                                                    <div class="course-fu">{{ $learningModeLabel }}</div>
                                                                @endif
                                                            </div>
                                                        @endforeach

                                                        @if($cellMarkers->isNotEmpty())
                                                            <div class="markers">
                                                                @foreach($cellMarkers as $marker)
                                                                    <span class="marker" title="{{ $marker['title'] ?? '' }}">{{ $marker['label'] ?? '' }}</span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endforeach
                    </section>
                @endforeach
            </div>

        </div>
    </main>
    @endforeach

    @if($printCourseList && $courseDirectory->isNotEmpty())
        <div class="pdf-page-courses">
            <div class="courses-header">
                <h1 class="courses-title">Kursliste</h1>
                <div class="courses-meta">
                    @foreach(array_filter([$data['schoolyear'] ?? null, $data['student'] ?? null, $courseDirectory->count() . ' ' . ($courseDirectory->count() === 1 ? 'Kurs' : 'Kurse'), $data['generated_at'] ?? null]) as $meta)
                        <span>{{ $meta }}</span>@if(! $loop->last)<span> &middot; </span>@endif
                    @endforeach
                </div>
            </div>
            <table class="courses-table">
                <thead>
                    <tr>
                        <th class="col-directory-label">Kurs</th>
                        <th class="col-directory-hints">Hinweise</th>
                        <th class="col-directory-details">Details</th>
                        <th class="col-directory-slots">Termine</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($courseDirectory as $entry)
                        <tr>
                            <td class="cell-label">{{ $entry['label'] }}</td>
                            <td class="cell-hints">
                                @foreach($entry['hints'] as $hint)
                                    <span class="course-hint">{{ $hint }}</span>
                                @endforeach
                            </td>
                            <td class="cell-details">{!! $formatDetailsHtml($entry['details']) !!}</td>
                            <td>{{ $entry['slots'] }}</td>
                        </tr>
                        @if(!empty($entry['date_groups']))
                            <tr class="directory-dates-row">
                                <td></td>
                                <td colspan="3" class="directory-dates-cell">
                                    @foreach($entry['date_groups'] as $dateGroup)
                                        <div class="directory-date-group">
                                            @if(trim((string) ($dateGroup['weekday'] ?? '')) !== '')
                                                <span class="directory-date-weekday">{{ $dateGroup['weekday'] }}:</span>
                                            @endif
                                            @foreach($dateGroup['dates'] as $date)
                                                <span class="directory-date">{{ $date }}</span>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($printCourseOverview && $courseOverviewSemesters->isNotEmpty())
        <div class="pdf-page-courses">
            <div class="courses-header">
                <h1 class="courses-title">Kursübersicht</h1>
                <div class="courses-meta">
                    @foreach(array_filter([$data['schoolyear'] ?? null, $data['student'] ?? null, $data['generated_at'] ?? null]) as $meta)
                        <span>{{ $meta }}</span>@if(! $loop->last)<span> &middot; </span>@endif
                    @endforeach
                </div>
            </div>

            @foreach($courseOverviewSemesters as $semesterLabel => $slots)
                <div class="courses-semester-title">
                    {{ $semesterLabel }}
                    @if($slots->first()['semester_range'] ?? '')
                        <span>({{ $slots->first()['semester_range'] }})</span>
                    @endif
                </div>
                <table class="courses-table">
                    <thead>
                        <tr>
                            <th class="col-weekday">Tag</th>
                            <th class="col-hour">Std.</th>
                            <th class="col-time">Zeit</th>
                            <th class="col-label">Kurs</th>
                            <th class="col-hints">Hinweise</th>
                            <th class="col-details">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slots as $slot)
                            @php
                                $slotHints = $courseDirectoryHintsByKey->get($courseDirectoryKey($slot), []);
                            @endphp
                            <tr>
                                <td class="cell-weekday">{{ $slot['weekday'] }}</td>
                                <td class="cell-hour">{{ $slot['hour_label'] ?? (($slot['hour'] ?? '') . '.') }}</td>
                                <td class="cell-time">{{ $slot['time'] }}</td>
                                <td class="cell-label">{{ $slot['label'] }}</td>
                                <td class="cell-hints">
                                    @foreach($slotHints as $hint)
                                        <span class="course-hint">{{ $hint }}</span>
                                    @endforeach
                                </td>
                                <td class="cell-details">{!! $formatDetailsHtml($slot['details'], false, true) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    @endif
</body>
</html>
