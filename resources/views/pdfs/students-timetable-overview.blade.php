<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] ?? 'Stundenplan' }}</title>
    @php
        $printOptions = $data['print_options'] ?? [];
        $printSingleWeeks = ($printOptions['single_weeks'] ?? false) === true;
        $printCourseList = ($printOptions['course_list'] ?? true) !== false;
        $isManualTimetable = ! empty($data['manual_cover']);
        $printSubjectOverview = $isManualTimetable || ($printOptions['course_overview'] ?? true) !== false;
        $pdfCourseNames = [
            'BOKS' => 'Bosnisch/Kroatisch/Serbisch',
            'BU' => 'Biologie',
            'CH' => 'Chemie',
            'D' => 'Deutsch',
            'D_DK' => 'Deutsch',
            'DAF' => 'Deutsch als Fremdsprache',
            'E' => 'Englisch',
            'E_DK' => 'Englisch',
            'ETH' => 'Ethik',
            'F' => 'Französisch',
            'GPB' => 'Geschichte und Politische Bildung',
            'GUS' => 'Gesundheit und Soziales',
            'GWB' => 'Geografie und wirtschaftliche Bildung',
            'INF' => 'Informatik',
            'KG' => 'Kunst und Gestaltung',
            'L' => 'Latein',
            'LET' => 'Lern- und Präsentationstechniken',
            'LPT' => 'Lern- und Präsentationstechniken',
            'M' => 'Mathematik',
            'M_DK' => 'Mathematik',
            'MU' => 'Musikerziehung',
            'OKON' => 'Ökonomie und Ökologie',
            'PH' => 'Physik',
            'PP' => 'Philosophie/Psychologie',
            'REV' => 'Religion evangelisch',
            'RIS' => 'Religion Islam',
            'RK' => 'Religion katholisch',
            'ROR' => 'Religion orthodox',
            'SPA' => 'Spanisch',
        ];
        $formatPdfCourseName = function (?string $label, ?string $identifier = null) use ($pdfCourseNames): string {
            $label = trim((string) $label);
            if ($label === '') {
                return '';
            }

            $normalizedLabel = mb_strtoupper($label);
            if (isset($pdfCourseNames[$normalizedLabel])) {
                $courseName = $pdfCourseNames[$normalizedLabel];
                $identifierPrefix = preg_split('/\s*-\s*/u', trim((string) $identifier), 2)[0] ?? '';

                if (preg_match('/^([\p{L}_]+)\s*(\d+(?:\.\d+)?)$/u', $identifierPrefix, $identifierMatches) === 1) {
                    $identifierCourseName = $pdfCourseNames[mb_strtoupper($identifierMatches[1])] ?? null;
                    if ($identifierCourseName !== null && mb_strtoupper($identifierCourseName) === mb_strtoupper($courseName)) {
                        return mb_strtoupper("{$courseName} {$identifierMatches[2]}");
                    }
                }

                return mb_strtoupper($courseName);
            }

            if (preg_match('/^([\p{L}_]+)\s*(\d+(?:\.\d+)?)$/u', $label, $matches) === 1) {
                $courseName = $pdfCourseNames[mb_strtoupper($matches[1])] ?? null;
                if ($courseName !== null) {
                    return mb_strtoupper("{$courseName} {$matches[2]}");
                }
            }

            return $label;
        };
        $formatPdfCourseShortName = function (array $course) use ($pdfCourseNames): string {
            $identifier = trim((string) ($course['identifier'] ?? ''));
            $label = trim((string) ($course['label'] ?? ''));
            $normalizedLabel = mb_strtoupper($label);

            if (isset($pdfCourseNames[$normalizedLabel])) {
                return $normalizedLabel;
            }

            if (preg_match('/^([\p{L}_]+)\s*\d+(?:\.\d+)?$/u', $label, $matches) === 1
                && isset($pdfCourseNames[mb_strtoupper($matches[1])])) {
                return $normalizedLabel;
            }

            $source = $identifier !== '' ? $identifier : $label;
            $shortName = preg_split('/\s*-\s*/u', $source, 2)[0] ?? $source;

            return mb_strtoupper(trim($shortName));
        };
        $weekdayLabels = collect($data['weekdays'] ?? [])->pluck('label')->all();
        $hasSaturdayColumn = collect($weekdayLabels)
            ->contains(fn ($weekdayLabel): bool => mb_strtolower(trim((string) $weekdayLabel)) === 'samstag');
        $manualBodyFontSize = $hasSaturdayColumn ? 6.5 : 7;
        $manualHeaderFontSize = $hasSaturdayColumn ? 7 : 7.5;
        $manualDetailFontSize = $hasSaturdayColumn ? 5.25 : 5.75;
        $manualCourseLabelFontSize = $hasSaturdayColumn ? 7 : 8;
        $manualTimeFontSize = $hasSaturdayColumn ? 7.5 : 8;
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
        $isBlockCourse = fn (array $course): bool => ! empty($course['is_block'])
            || preg_match('/\bBlock(?:unterricht)?\b/iu', (string) ($course['details'] ?? '')) === 1;
        $courseLearningModeLabel = fn (array $course): string => collect([
            $isCompactCourse($course)
                ? 'Kompaktunterricht'
                : (! empty($course['is_fu']) ? 'Fernunterricht' : null),
            $isBlockCourse($course) ? 'Block' : null,
        ])->filter()->unique()->implode(' · ');
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
        $rowHeight = $isManualTimetable && $hourRowCount < 15
            ? max($baseRowHeight, min(20, $availableRowHeight / $hourRowCount))
            : $baseRowHeight;
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

        $allCourseSlots = collect();
        $manualNumberedGroups = collect();
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
                        $cellCourses = collect($cl['courses'] ?? [])->values();
                        $cellMarkers = collect($cl['markers'] ?? [])
                            ->filter(fn (array $marker): bool => preg_match('/^!?\d+$/u', trim((string) ($marker['label'] ?? ''))) === 1)
                            ->values();

                        if ($isManualTimetable && $cellCourses->count() > 1 && $cellMarkers->isNotEmpty()) {
                            foreach ($cellMarkers as $marker) {
                                $manualNumberedGroups->push([
                                    'reference' => trim((string) ($marker['label'] ?? '')),
                                    'title' => trim((string) ($marker['title'] ?? '')),
                                    'weekday' => $weekdayLabels[$cellIdx] ?? '',
                                    'hour' => (int) ($hr['hour'] ?? 0),
                                    'time' => collect([$timeFrom, $timeUntil])
                                        ->filter()
                                        ->implode(' - '),
                                    'courses' => $cellCourses->map(function (array $course) use ($formatPdfCourseName): array {
                                        $overlapDates = collect($course['overlap_dates'] ?? [])
                                            ->map(fn ($date): string => trim((string) $date))
                                            ->filter()
                                            ->unique()
                                            ->values();

                                        return [
                                            'identifier' => trim((string) ($course['identifier'] ?? '')),
                                            'label' => $formatPdfCourseName($course['label'] ?? null, $course['identifier'] ?? null),
                                            'dates' => collect($course['dates'] ?? [])
                                                ->map(fn ($date): string => trim((string) $date))
                                                ->filter()
                                                ->unique()
                                                ->sort()
                                                ->values()
                                                ->map(fn (string $date): array => [
                                                    'date' => $date,
                                                    'is_overlap' => $overlapDates->contains($date),
                                                ])
                                                ->all(),
                                        ];
                                    })->all(),
                                ]);
                            }
                        }

                        foreach ($cellCourses as $crs) {
                            $crsLabel = $formatPdfCourseName($crs['label'] ?? null, $crs['identifier'] ?? null);
                            if ($crsLabel === '') {
                                continue;
                            }
                            $isCompact = $isCompactCourse($crs);
                            $allCourseSlots->push([
                                'label' => $crsLabel,
                                'short_label' => $formatPdfCourseShortName($crs),
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
                                'is_block' => $isBlockCourse($crs),
                                'recurrence_label' => trim((string) ($crs['recurrence_label'] ?? '')),
                                'recurrence_interval' => (int) ($crs['recurrence_interval'] ?? 0),
                            ]);
                        }
                    }
                }
            }
        }
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
            $slot['short_label'] ?? '',
            $slotRecurrenceSignature($slot),
        ]);

        $allCourseSlots = $allCourseSlots
            ->unique(fn (array $c): string => implode('|', [
                $c['semester'],
                $c['weekday'],
                $c['hour'],
                $c['label'],
                $c['short_label'],
                $slotRecurrenceSignature($c),
            ]))
            ->sortBy([['weekday_index', 'asc'], ['hour', 'asc'], ['label', 'asc']])
            ->values();
        $subjectOverviewWeekdays = collect([
            0 => 'Montag',
            1 => 'Dienstag',
            2 => 'Mittwoch',
            3 => 'Donnerstag',
            4 => 'Freitag',
            5 => 'Samstag',
        ]);
        $compactSubjectOverviewTimes = function ($slots): string {
            $compactedTimeRanges = [];

            foreach ($slots as $slot) {
                $timeRange = str_replace('–', '-', trim((string) ($slot['time'] ?? '')));
                $hour = (int) ($slot['hour'] ?? 0);
                if ($timeRange === '') {
                    continue;
                }

                if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/u', $timeRange, $matches) !== 1) {
                    $compactedTimeRanges[] = ['label' => $timeRange];

                    continue;
                }

                $lastIndex = array_key_last($compactedTimeRanges);
                if ($lastIndex !== null
                    && isset($compactedTimeRanges[$lastIndex]['last_hour'])
                    && $compactedTimeRanges[$lastIndex]['last_hour'] + 1 === $hour) {
                    $compactedTimeRanges[$lastIndex]['until'] = $matches[2];
                    $compactedTimeRanges[$lastIndex]['last_hour'] = $hour;
                    $compactedTimeRanges[$lastIndex]['label'] = "{$compactedTimeRanges[$lastIndex]['from']} - {$matches[2]}";

                    continue;
                }

                $compactedTimeRanges[] = [
                    'from' => $matches[1],
                    'until' => $matches[2],
                    'last_hour' => $hour,
                    'label' => "{$matches[1]} - {$matches[2]}",
                ];
            }

            return collect($compactedTimeRanges)
                ->pluck('label')
                ->unique()
                ->implode(', ');
        };
        $subjectOverviewHints = function ($slots) use ($courseTwoWeekParitySuffix, $slotRecurrenceInterval): array {
            $twoWeeklyHints = $slots
                ->filter(fn (array $slot): bool => $slotRecurrenceInterval($slot) === 2)
                ->map(function (array $slot) use ($courseTwoWeekParitySuffix): string {
                    $details = (string) ($slot['details'] ?? '');
                    $parity = preg_match('/\b2\s*-?\s*w(?:öchig|öching|ochig)?\s+([AB])\b/iu', $details, $matches) === 1
                        ? mb_strtoupper($matches[1])
                        : trim($courseTwoWeekParitySuffix((array) ($slot['dates'] ?? [])));

                    return '2-wöchig'.($parity !== '' ? " {$parity}" : '');
                })
                ->unique()
                ->values();

            return collect([
                $slots->contains(fn (array $slot): bool => ! empty($slot['is_fu'])) ? 'Fernunterricht' : null,
                ...$twoWeeklyHints->all(),
                $slots->contains(fn (array $slot): bool => ! empty($slot['is_compact'])) ? 'Kompaktunterricht' : null,
                $slots->contains(fn (array $slot): bool => ! empty($slot['is_block'])) ? 'Block' : null,
            ])->filter()->unique()->values()->all();
        };
        $subjectOverviewHintDates = function ($slots) use ($parseCourseWeekParityDate, $slotRecurrenceInterval): array {
            return $slots
                ->filter(fn (array $slot): bool => $slotRecurrenceInterval($slot) === 2 || ! empty($slot['is_block']))
                ->flatMap(fn (array $slot): array => (array) ($slot['dates'] ?? []))
                ->map(fn ($date): ?\Carbon\Carbon => $parseCourseWeekParityDate((string) $date))
                ->filter()
                ->unique(fn (\Carbon\Carbon $date): string => $date->toDateString())
                ->sortBy(fn (\Carbon\Carbon $date): string => $date->toDateString())
                ->map(fn (\Carbon\Carbon $date): string => $date->format('d.m.'))
                ->values()
                ->all();
        };
        $subjectOverviewRows = $allCourseSlots
            ->filter(fn (array $slot): bool => (int) ($slot['hour'] ?? 0) > 0)
            ->groupBy(fn (array $slot): string => implode('|', [
                $slot['label'] ?? '',
                $slot['short_label'] ?? '',
                $slot['weekday_index'] ?? '',
            ]))
            ->map(function ($slots) use ($compactSubjectOverviewTimes, $subjectOverviewHintDates, $subjectOverviewHints, $subjectOverviewWeekdays): array {
                $slots = $slots
                    ->sortBy('hour')
                    ->unique('hour')
                    ->values();
                $firstSlot = $slots->first();
                $weekdayIndex = (int) ($firstSlot['weekday_index'] ?? -1);

                return [
                    'course_name' => $firstSlot['label'] ?? '',
                    'short_name' => $firstSlot['short_label'] ?? '',
                    'weekday' => $subjectOverviewWeekdays->get($weekdayIndex, $firstSlot['weekday'] ?? ''),
                    'weekday_index' => $weekdayIndex,
                    'hours' => $slots
                        ->map(fn (array $slot): string => ((int) ($slot['hour'] ?? 0)).'.')
                        ->implode(', '),
                    'times' => $compactSubjectOverviewTimes($slots),
                    'hints' => $subjectOverviewHints($slots),
                    'hint_dates' => $subjectOverviewHintDates($slots),
                ];
            })
            ->sortBy(fn (array $row): string => implode('|', [
                mb_strtolower((string) $row['course_name']),
                mb_strtolower((string) $row['short_name']),
                str_pad((string) $row['weekday_index'], 2, '0', STR_PAD_LEFT),
            ]), SORT_NATURAL)
            ->values();
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
        $formatManualSummaryDate = function (string $date) use ($parseTimetableDate): string {
            $parsedDate = $parseTimetableDate($date);

            return $parsedDate?->format('d.m.') ?? $date;
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

        .pdf-cover-page {
            box-sizing: border-box;
            width: 257mm;
            padding: 11mm 14mm;
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-after: always;
            break-after: page;
            background: linear-gradient(145deg, #fff7ed 0%, #ffffff 58%, #f8fafc 100%);
            border-top: 3mm solid #c2410c;
        }

        .pdf-cover-kicker {
            margin-bottom: 4mm;
            color: #c2410c;
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .pdf-cover-title {
            margin: 0 0 10mm;
            color: #0f172a;
            font-size: 28pt;
            line-height: 1.08;
        }

        .pdf-cover-information {
            width: 100%;
            margin: 0 0 7mm;
            border-spacing: 3mm;
            table-layout: fixed;
        }

        .pdf-cover-information-item {
            padding: 4mm 5mm;
            vertical-align: top;
            background: rgba(255, 255, 255, 0.88);
            border: 0.35mm solid #e2e8f0;
            border-radius: 2mm;
        }

        .pdf-cover-information-label {
            display: block;
            margin-bottom: 1.4mm;
            color: #64748b;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .pdf-cover-information-value {
            color: #1e293b;
            font-size: 12pt;
            font-weight: 700;
        }

        .pdf-cover-study-selection {
            margin: 0 0 6mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .pdf-cover-study-selection-title {
            margin: 0 0 2mm;
            color: #1e3a8a;
            font-size: 12pt;
        }

        .pdf-cover-study-selection-table {
            width: 100%;
            border-spacing: 2mm;
            table-layout: fixed;
        }

        .pdf-cover-study-selection-item {
            padding: 2.5mm 4mm;
            vertical-align: top;
            background: #eff6ff;
            border: 0.35mm solid #bfdbfe;
            border-radius: 2mm;
        }

        .pdf-cover-study-selection-label {
            display: block;
            margin-bottom: 1mm;
            color: #1d4ed8;
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .pdf-cover-study-selection-value {
            color: #1e293b;
            font-size: 10pt;
            font-weight: 700;
        }

        .pdf-cover-disclaimer {
            padding: 5mm 6mm;
            color: #78350f;
            background: #fffbeb;
            border: 0.5mm solid #f59e0b;
            border-radius: 2.5mm;
            font-size: 10.5pt;
            line-height: 1.45;
        }

        .pdf-cover-disclaimer strong {
            display: block;
            margin-bottom: 1.5mm;
            font-size: 12pt;
        }

        .pdf-cover-reminder {
            margin-top: 7mm;
            padding: 5mm;
            page-break-inside: avoid;
            break-inside: avoid;
            color: #ffffff;
            background: #c2410c;
            border-radius: 2.5mm;
            font-size: 20pt;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-align: center;
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

        .pdf-page--manual-timetable th,
        .pdf-page--manual-timetable td {
            text-align: center;
            vertical-align: middle;
        }

        .pdf-page--manual-timetable td {
            height: {{ number_format($rowHeight, 2, '.', '') }}mm;
            max-height: {{ number_format($rowHeight, 2, '.', '') }}mm;
            padding: 0.25mm 0.4mm 0.45mm;
            font-size: {{ number_format($manualBodyFontSize, 2, '.', '') }}pt;
            line-height: 1;
        }

        .pdf-page--manual-timetable .cell-content {
            height: auto;
            max-height: {{ number_format(max(3.5, $rowHeight - 1.7), 2, '.', '') }}mm;
            overflow: hidden;
        }

        .pdf-page--manual-timetable .header {
            margin-bottom: 0.8mm;
        }

        .pdf-page--manual-timetable .title {
            font-size: 10pt;
        }

        .pdf-page--manual-timetable .meta {
            margin-top: 0.2mm;
            font-size: 7.5pt;
            line-height: 1;
        }

        .pdf-page--manual-timetable .semester-title {
            margin-bottom: 0.2mm;
            font-size: 6pt;
            line-height: 1;
        }

        .pdf-page--manual-timetable th {
            height: 2.8mm;
            padding: 0.25mm 0.4mm;
            font-size: {{ number_format($manualHeaderFontSize, 2, '.', '') }}pt;
            line-height: 1;
        }

        .pdf-page--manual-timetable .course-identifier,
        .pdf-page--manual-timetable .course-details,
        .pdf-page--manual-timetable .course-fu,
        .pdf-page--manual-timetable .course-more,
        .pdf-page--manual-timetable .marker {
            font-size: {{ number_format($manualDetailFontSize, 2, '.', '') }}pt;
            line-height: 1;
        }

        .pdf-page--manual-timetable td.time-cell,
        .pdf-page--manual-timetable .time-range {
            font-size: {{ number_format($manualTimeFontSize, 2, '.', '') }}pt;
        }

        .pdf-page--manual-timetable .course-label {
            display: block;
            width: auto;
        }

        .pdf-page--manual-timetable .course-label-main {
            display: block;
            overflow: visible;
            font-size: {{ number_format($manualCourseLabelFontSize, 2, '.', '') }}pt;
            text-overflow: clip;
            white-space: normal;
            width: auto;
        }

        .pdf-page--manual-timetable .course-identifier {
            display: block;
            margin: 0.2mm 0 0;
            width: auto;
        }

        .pdf-page--manual-timetable .courses-grid--two-columns {
            display: table;
            table-layout: fixed;
            width: 100%;
        }

        .pdf-page--manual-timetable .courses-grid--two-columns .courses-grid-row {
            display: table-row;
        }

        .pdf-page--manual-timetable .courses-grid--two-columns .course-grid-item {
            display: table-cell;
            padding-right: 0.5mm;
            vertical-align: top;
            width: 50%;
        }

        .pdf-page--manual-timetable .courses-grid--two-columns .course-grid-item + .course-grid-item {
            border-left: 0.15mm solid rgba(100, 116, 139, 0.35);
            padding-left: 0.5mm;
            padding-right: 0;
        }

        .pdf-page--manual-timetable .courses-grid--two-columns .course-grid-row + .courses-grid-row .course {
            margin-top: 0.15mm;
        }

        .pdf-page--manual-timetable .courses-grid-row + .courses-grid-row .course {
            margin-top: 0.35mm;
            padding-top: 0.35mm;
            border-top: 0.15mm solid rgba(100, 116, 139, 0.35);
        }

        .pdf-page--manual-timetable .courses-grid--stacked .course-information-row {
            margin-top: 0.1mm;
        }

        .pdf-page--manual-timetable .course-information-row {
            margin-top: 0.25mm;
            overflow: hidden;
            color: #1d4ed8;
            font-size: {{ number_format($manualDetailFontSize, 2, '.', '') }}pt;
            font-weight: 700;
            line-height: 1.05;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pdf-page--manual-timetable .course-information-row .course-details,
        .pdf-page--manual-timetable .course-information-row .course-fu,
        .pdf-page--manual-timetable .course-information-row .course-detail-line {
            display: inline;
            margin: 0;
            overflow: visible;
            color: #1d4ed8;
            font-size: inherit;
            font-weight: 700;
            line-height: inherit;
            text-overflow: clip;
            white-space: nowrap;
        }

        .pdf-page--manual-timetable .course-information-row .course-details + .course-fu::before {
            content: " ";
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

        .course-identifier {
            margin-top: 0.2mm;
            overflow: hidden;
            color: #334155;
            font-size: var(--pdf-detail-font-size);
            font-weight: 700;
            line-height: 1.1;
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

        .subject-overview-page .header {
            margin-bottom: 3mm;
        }

        .subject-overview-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            color: #0f172a;
            font-size: 8pt;
            line-height: 1.3;
        }

        .subject-overview-table th {
            padding: 1.2mm 1.5mm;
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 7.5pt;
            font-weight: 700;
            text-align: left;
            border: none;
            border-bottom: 0.4mm solid #93c5fd;
        }

        .subject-overview-table td {
            padding: 1.2mm 1.5mm;
            background: #ffffff;
            border: none;
            border-bottom: 0.2mm solid #e2e8f0;
            vertical-align: middle;
        }

        .subject-overview-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .subject-overview-course-name {
            color: #172554;
            font-weight: 700;
        }

        .subject-overview-short-name,
        .subject-overview-hours {
            color: #1e3a8a;
            font-weight: 700;
        }

        .subject-overview-day,
        .subject-overview-times {
            color: #475569;
        }

        .subject-overview-hints {
            color: #1d4ed8;
            font-weight: 700;
        }

        .subject-overview-hint {
            display: block;
            white-space: nowrap;
        }

        .subject-overview-hint-dates {
            display: block;
            margin-top: 0.5mm;
            color: #475569;
            font-size: 7pt;
            font-weight: 400;
            line-height: 1.2;
        }

        .subject-overview-table .col-subject-name { width: 23%; }
        .subject-overview-table .col-subject-short-name { width: 11%; }
        .subject-overview-table .col-subject-day { width: 12%; }
        .subject-overview-table .col-subject-hours { width: 12%; }
        .subject-overview-table .col-subject-times { width: 20%; }
        .subject-overview-table .col-subject-hints { width: 22%; }

        .manual-numbered-summary-group {
            margin-bottom: 3mm;
            page-break-inside: avoid;
            break-inside: avoid;
            background: #f8fafc;
            border: 0.3mm solid #cbd5e1;
            border-left: 1mm solid #64748b;
            border-radius: 1.5mm;
        }

        .manual-numbered-summary-group--overlap {
            background: #fffbeb;
            border-color: #fcd34d;
            border-left-color: #d97706;
        }

        .manual-numbered-summary-heading {
            padding: 1.6mm 2mm 1.2mm;
            color: #334155;
            font-size: 8pt;
            font-weight: 700;
            line-height: 1.3;
            border-bottom: 0.2mm solid #e2e8f0;
        }

        .manual-numbered-summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .manual-numbered-summary-table td {
            padding: 1.4mm 2mm;
            vertical-align: top;
            font-size: 8pt;
            line-height: 1.4;
            border: none;
            border-bottom: 0.2mm solid #e2e8f0;
        }

        .manual-numbered-summary-table tr:last-child td {
            border-bottom: none;
        }

        .manual-numbered-summary-reference-cell {
            width: 8%;
        }

        .manual-numbered-summary-course-cell {
            width: 30%;
        }

        .manual-numbered-summary-dates-cell {
            width: 62%;
        }

        .manual-numbered-summary-reference {
            display: inline-block;
            min-width: 7mm;
            padding: 0.6mm 1mm;
            color: #334155;
            background: #ffffff;
            border: 0.3mm solid #64748b;
            border-radius: 1mm;
            font-weight: 700;
            text-align: center;
        }

        .manual-numbered-summary-group--overlap .manual-numbered-summary-reference {
            color: #92400e;
            border-color: #d97706;
        }

        .manual-numbered-summary-identifier {
            display: inline-block;
            margin-right: 1.4mm;
            color: #1e3a8a;
            font-weight: 700;
            line-height: 1.4;
            vertical-align: middle;
        }

        .manual-numbered-summary-course-title {
            display: inline-block;
            color: #0f172a;
            font-weight: 700;
            line-height: 1.4;
            vertical-align: middle;
        }

        .manual-numbered-summary-date {
            display: inline-block;
            margin: 0 2mm 0.5mm 0;
            color: #334155;
            white-space: nowrap;
        }

        .manual-numbered-summary-date--overlap {
            color: #b42318;
            font-weight: 700;
        }

        .manual-numbered-summary-date--missing {
            color: #64748b;
            font-style: italic;
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
    @if(! empty($data['manual_cover']))
        <main class="pdf-cover-page">
            <div class="pdf-cover-kicker">Stundenplanung</div>
            <h1 class="pdf-cover-title">Allgemeine Informationen</h1>

            <table class="pdf-cover-information">
                <tbody>
                    <tr>
                        <td class="pdf-cover-information-item">
                            <span class="pdf-cover-information-label">Studierende/r</span>
                            <span class="pdf-cover-information-value">{{ $data['student'] ?? 'Ohne Studierendenbezug' }}</span>
                        </td>
                        <td class="pdf-cover-information-item">
                            <span class="pdf-cover-information-label">Schuljahr</span>
                            <span class="pdf-cover-information-value">{{ $data['schoolyear'] ?? '–' }}</span>
                        </td>
                        <td class="pdf-cover-information-item">
                            <span class="pdf-cover-information-label">Erstellt am</span>
                            <span class="pdf-cover-information-value">{{ $data['generated_at'] ?? '–' }}</span>
                        </td>
                        @if(! empty($data['subtitle']))
                            <td class="pdf-cover-information-item">
                                <span class="pdf-cover-information-label">Umfang</span>
                                <span class="pdf-cover-information-value">{{ $data['subtitle'] }}</span>
                            </td>
                        @endif
                    </tr>
                </tbody>
            </table>

            @if(! empty($data['study_selections']))
                <section class="pdf-cover-study-selection">
                    <h2 class="pdf-cover-study-selection-title">Studienauswahl</h2>
                    <table class="pdf-cover-study-selection-table">
                        <tbody>
                            @foreach(collect($data['study_selections'])->chunk(2) as $selectionRow)
                                <tr>
                                    @foreach($selectionRow as $selection)
                                        <td class="pdf-cover-study-selection-item">
                                            <span class="pdf-cover-study-selection-label">{{ $selection['label'] }}</span>
                                            <span class="pdf-cover-study-selection-value">{{ $selection['value'] }}</span>
                                        </td>
                                    @endforeach
                                    @if($selectionRow->count() === 1)
                                        <td></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            <div class="pdf-cover-disclaimer">
                <strong>Wichtiger Hinweis</strong>
                Es wird keine Gewähr für die Richtigkeit, Vollständigkeit oder Überschneidungsfreiheit übernommen.
                Maßgeblich sind die offiziell gebuchten Lehrveranstaltungen und die veröffentlichten Termine.
            </div>

            <div class="pdf-cover-reminder">Buchen nicht vergessen!</div>
        </main>
    @endif

    @foreach($timetablePages as $timetablePage)
        @php
            $pageData = $timetablePage['data'];
            $pageClass = $timetablePage['is_additional'] ? 'pdf-page pdf-page--additional' : 'pdf-page';

            if (! empty($data['manual_cover'])) {
                $pageClass .= ' pdf-page--manual-timetable';
            }
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
                        @unless($isManualTimetable)
                            <div class="semester-title">
                                {{ $semester['label'] ?? 'Semester' }}
                                @if(! empty($semester['date_range']))
                                    <span>({{ $semester['date_range'] }})</span>
                                @endif
                            </div>
                        @endunless

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
                                                            $usesTwoColumnCourses = $hasDenseCourses && ! $isManualTimetable;
                                                            $usesStackedCourses = $hasDenseCourses && $isManualTimetable;
                                                            $shownCourses = $courses;
                                                        @endphp

                                                        <div @class([
                                                            'courses-grid',
                                                            'courses-grid--two-columns' => $usesTwoColumnCourses,
                                                            'courses-grid--stacked' => $usesStackedCourses,
                                                        ])>
                                                            @foreach($shownCourses->chunk($usesTwoColumnCourses ? 2 : 1) as $courseRow)
                                                                <div class="courses-grid-row">
                                                                    @foreach($courseRow as $course)
                                                                        <div class="course-grid-item">
                                                                            <div class="course @if($usesTwoColumnCourses) course--compact @endif">
                                                                                @php
                                                                                    $titleLabel = $formatPdfCourseName(
                                                                                        $isManualTimetable
                                                                                            ? ($course['label'] ?? null)
                                                                                            : $courseTitleLabel($course),
                                                                                        $course['identifier'] ?? null
                                                                                    );
                                                                                    $courseDetails = $courseDetailsForRendering($course);
                                                                                    $isCompactCourseForRendering = $isCompactCourse($course);
                                                                                    $learningModeLabel = $courseLearningModeLabel($course);
                                                                                    $titleContext = $isManualTimetable ? '' : $courseTitleContext($course);
                                                                                    $courseIdentifier = $isManualTimetable
                                                                                        ? trim((string) ($course['identifier'] ?? ''))
                                                                                        : '';
                                                                                    if ($isCompactCourseForRendering) {
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
                                                                                @if($courseIdentifier !== '')
                                                                                    <div class="course-identifier">{{ $courseIdentifier }}</div>
                                                                                @endif
                                                                                @if($courseDetails !== '' || $learningModeLabel !== '')
                                                                                    <div class="course-information-row">
                                                                                        @if($courseDetails !== '')
                                                                                            <div class="course-details">{!! $formatDetailsHtml($courseDetails, false, $timetablePage['is_additional'], $isCompactCourseForRendering) !!}</div>
                                                                                        @endif
                                                                                        @if($learningModeLabel !== '')
                                                                                            <div class="course-fu">{{ $learningModeLabel }}</div>
                                                                                        @endif
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                    @if($hasDenseCourses && $courseRow->count() === 1)
                                                                        <div class="course-grid-item"></div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>

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

    @if($isManualTimetable && $manualNumberedGroups->isNotEmpty())
        <div class="pdf-page-courses manual-numbered-summary-page">
            <div class="header">
                <h1 class="title">Überschneidungen</h1>
                <div class="meta">
                    @foreach(array_filter([$data['schoolyear'] ?? null, $data['student'] ?? null, $data['subtitle'] ?? null, $data['generated_at'] ?? null]) as $meta)
                        <span>{{ $meta }}</span>@if(! $loop->last)<span> &middot; </span>@endif
                    @endforeach
                </div>
            </div>

            @foreach($manualNumberedGroups as $numberedGroup)
                @php
                    $manualSummaryHeadingContext = collect([
                        $numberedGroup['title'] ?: 'Mehrfachbelegung',
                        $numberedGroup['weekday'] ?? null,
                        ! empty($numberedGroup['hour']) ? $numberedGroup['hour'].'. Std.' : null,
                    ])->filter()->implode(' · ');
                    $manualSummaryHeading = collect([
                        $manualSummaryHeadingContext,
                        $numberedGroup['time'] ?? null,
                    ])->filter()->implode(' ');
                @endphp
                <section class="manual-numbered-summary-group @if(str_starts_with($numberedGroup['reference'], '!')) manual-numbered-summary-group--overlap @endif">
                    <div class="manual-numbered-summary-heading">{{ $manualSummaryHeading }}:</div>
                    <table class="manual-numbered-summary-table">
                        <tbody>
                            @foreach($numberedGroup['courses'] as $course)
                                <tr>
                                    <td class="manual-numbered-summary-reference-cell">
                                        <span class="manual-numbered-summary-reference">{{ $numberedGroup['reference'] }}</span>
                                    </td>
                                    <td class="manual-numbered-summary-course-cell">
                                        @if($course['identifier'] !== '')
                                            <span class="manual-numbered-summary-identifier">{{ $course['identifier'] }}</span>
                                        @endif
                                        <span class="manual-numbered-summary-course-title">{{ $course['label'] }}</span>
                                    </td>
                                    <td class="manual-numbered-summary-dates-cell">
                                        @forelse($course['dates'] as $dateItem)
                                            <span class="manual-numbered-summary-date{{ ! empty($dateItem['is_overlap']) ? ' manual-numbered-summary-date--overlap' : '' }}">{{ $formatManualSummaryDate((string) ($dateItem['date'] ?? '')) }}</span>
                                        @empty
                                            <span class="manual-numbered-summary-date manual-numbered-summary-date--missing">
                                                Keine exakten Termine verfügbar
                                            </span>
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>
    @endif

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

    @if($printSubjectOverview && $subjectOverviewRows->isNotEmpty())
        <div class="pdf-page-courses subject-overview-page">
            <div class="header">
                <h1 class="title">Fächerübersicht</h1>
                <div class="meta">
                    @foreach(array_filter([$data['schoolyear'] ?? null, $data['student'] ?? null, $data['generated_at'] ?? null]) as $meta)
                        <span>{{ $meta }}</span>@if(! $loop->last)<span> &middot; </span>@endif
                    @endforeach
                </div>
            </div>
            <table class="subject-overview-table">
                <thead>
                    <tr>
                        <th class="col-subject-name">Fachname</th>
                        <th class="col-subject-short-name">Kurzname</th>
                        <th class="col-subject-day">Tag</th>
                        <th class="col-subject-hours">Stunde(n)</th>
                        <th class="col-subject-times">Zeit(en)</th>
                        <th class="col-subject-hints">Hinweise</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($subjectOverviewRows as $subjectOverviewRow)
                        <tr>
                            <td class="subject-overview-course-name">{{ $subjectOverviewRow['course_name'] }}</td>
                            <td class="subject-overview-short-name">{{ $subjectOverviewRow['short_name'] }}</td>
                            <td class="subject-overview-day">{{ $subjectOverviewRow['weekday'] }}</td>
                            <td class="subject-overview-hours">{{ $subjectOverviewRow['hours'] }}</td>
                            <td class="subject-overview-times">{{ $subjectOverviewRow['times'] }}</td>
                            <td class="subject-overview-hints">
                                @forelse($subjectOverviewRow['hints'] as $hint)
                                    <span class="subject-overview-hint">{{ $hint }}</span>
                                @empty
                                    -
                                @endforelse
                                @if($subjectOverviewRow['hint_dates'] !== [])
                                    <span class="subject-overview-hint-dates">{{ implode(', ', $subjectOverviewRow['hint_dates']) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
