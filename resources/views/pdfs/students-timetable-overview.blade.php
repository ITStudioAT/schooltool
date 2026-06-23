<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] ?? 'Stundenplan' }}</title>
    @php
        $semesters = collect($data['semesters'] ?? []);
        $semesterCount = max(1, $semesters->count());
        $isTwoColumns = $semesterCount > 1;

        $semesterMetrics = $semesters->map(function (array $semester) {
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
        $rowHeight = max(4.2, min(8.5, $availableRowHeight / $hourRowCount));
        $naturalHeight = $headerHeight + $labelHeight + ($hourRowCount * $rowHeight);
        $scale = max(0.45, min(1, $pageHeight / $naturalHeight));
        $contentWidth = 100 / $scale;
        $visibleCourseLimit = 2;

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
                            $allCourseSlots->push([
                                'label' => $crsLabel,
                                'details' => trim((string) ($crs['details'] ?? '')),
                                'dates' => array_filter(array_map('trim', (array) ($crs['dates'] ?? []))),
                                'semester' => $semLabel,
                                'semester_range' => $semRange,
                                'weekday' => $weekdayLabels[$cellIdx] ?? '',
                                'weekday_index' => $cellIdx,
                                'hour' => (int) ($hr['hour'] ?? 0),
                                'time' => $timeRange,
                                'status' => $cellStatus,
                                'is_fu' => !empty($crs['is_fu']),
                            ]);
                        }
                    }
                }
            }
        }
        $allCourseSlots = $allCourseSlots
            ->unique(fn (array $c): string => "{$c['semester']}|{$c['weekday']}|{$c['hour']}|{$c['label']}")
            ->sortBy([['weekday_index', 'asc'], ['hour', 'asc'], ['label', 'asc']])
            ->values();
        $slotTimeParts = function (array $slot): array {
            return array_map(
                fn (string $time): string => trim($time),
                array_pad(preg_split('/\s*[–-]\s*/u', (string) ($slot['time'] ?? ''), 2) ?: [], 2, ''),
            );
        };
        $mergeSlotDetails = function (string $leftDetails = '', string $rightDetails = ''): string {
            return collect([$leftDetails, $rightDetails])
                ->flatMap(fn (string $details): array => preg_split('/\s*·\s*/u', $details) ?: [])
                ->map(fn (string $detail): string => trim($detail))
                ->filter()
                ->unique()
                ->values()
                ->implode(' · ');
        };

        $mergeCourseOverviewSlots = function ($slots) use ($slotTimeParts, $mergeSlotDetails) {
            $mergedSlots = collect();

            $slots
                ->sortBy([['weekday_index', 'asc'], ['hour', 'asc'], ['label', 'asc']])
                ->groupBy(fn (array $slot): string => implode('|', [
                    $slot['semester'] ?? '',
                    $slot['weekday'] ?? '',
                    $slot['label'] ?? '',
                    $slot['status'] ?? '',
                    !empty($slot['is_fu']) ? 'fu' : 'regular',
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
        $directoryDetails = function ($slots): string {
            $segments = $slots
                ->pluck('details')
                ->filter()
                ->flatMap(fn (string $details) => preg_split('/\s*·\s*/u', $details) ?: [])
                ->map(fn (string $segment): string => trim($segment))
                ->filter()
                ->unique()
                ->values();

            $recurrences = $segments
                ->filter(fn (string $segment): bool => preg_match('/^(\d+)\s*-?\s*w(?:öchig)?$/iu', $segment) === 1)
                ->map(fn (string $segment): string => preg_replace('/^(\d+)\s*-?\s*w(?:öchig)?$/iu', '$1-w', $segment))
                ->unique()
                ->sort()
                ->values();

            $remainingSegments = $segments
                ->reject(fn (string $segment): bool => preg_match('/^(\d+)\s*-?\s*w(?:öchig)?$/iu', $segment) === 1)
                ->reject(fn (string $segment): bool => preg_match('/^\d{1,2}\.\d{1,2}\.(\d{2,4})?$/u', $segment) === 1)
                ->values();

            $recurrence = $recurrences->isNotEmpty()
                ? $recurrences->implode(', ')
                : 'Einzeltermine';

            return collect([$recurrence, ...$remainingSegments])
                ->filter()
                ->unique()
                ->implode(' ');
        };

        $formatCourseDates = fn ($slots) => $slots
            ->flatMap(fn (array $slot): array => $slot['dates'] ?? [])
            ->map(fn (string $date): string => trim($date))
            ->filter()
            ->unique()
            ->sort()
            ->map(function (string $date): string {
                try {
                    return \Carbon\Carbon::parse($date)->format('d.m.');
                } catch (\Throwable) {
                    return $date;
                }
            })
            ->values()
            ->all();

        $courseDirectory = $allCourseSlots
            ->groupBy('label')
            ->map(function ($slots, string $label) use ($directorySlotSummary, $directoryDetails, $formatCourseDates) {
                return [
                    'label' => $label,
                    'details' => $directoryDetails($slots),
                    'slots' => $directorySlotSummary($slots),
                    'dates' => $formatCourseDates($slots),
                    'status' => $slots->contains('status', 'conflict') ? 'conflict'
                        : ($slots->contains('status', 'warning') ? 'warning'
                        : ($slots->contains('status', 'related') ? 'related' : 'filled')),
                    'is_fu' => $slots->contains('is_fu', true),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
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
            height: var(--pdf-row-height);
            padding: 0.4mm 0.5mm;
            background: #f8fafc;
            font-size: var(--pdf-body-font-size);
            line-height: 1.15;
            overflow: hidden;
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
            margin-top: 0.1mm;
        }

        .cell-content {
            position: relative;
            height: calc(var(--pdf-row-height) - 0.8mm);
            overflow: hidden;
        }

        .course-label {
            font-weight: 700;
            line-height: 1.15;
            word-break: break-word;
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
            white-space: nowrap;
        }

        .course-details {
            margin-top: 0.2mm;
            color: #475569;
            font-size: var(--pdf-detail-font-size);
            line-height: 1.1;
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
        .courses-table .col-label { width: 30%; }
        .courses-table .col-details { width: 34%; }
        .courses-table .col-status { width: 8%; text-align: center; }

        .courses-table .col-directory-status { width: 6%; text-align: center; }
        .courses-table .col-directory-label { width: 22%; }
        .courses-table .col-directory-details { width: 18%; }
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

        .courses-table .cell-details {
            color: #64748b;
        }

        .status-dot {
            display: inline-block;
            width: 2.2mm;
            height: 2.2mm;
            border-radius: 50%;
        }

        .status-dot--filled { background: #22c55e; }
        .status-dot--warning { background: #f97316; }
        .status-dot--conflict { background: #ef4444; }
        .status-dot--related { background: #f97316; }

        .fu-badge {
            display: inline-block;
            padding: 0 0.5mm;
            margin-left: 0.3mm;
            background: #dbeafe;
            color: #1e40af;
            font-size: 0.7em;
            font-weight: 700;
            line-height: 1.2;
            border-radius: 0.5mm;
            vertical-align: super;
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
    <main class="pdf-page">
        <div class="pdf-content">
            <div class="header">
                <h1 class="title">{{ $data['title'] ?? 'Stundenplan' }}</h1>
                <div class="meta">
                    @foreach(array_filter([$data['schoolyear'] ?? null, $data['student'] ?? null, $data['subtitle'] ?? null, $data['generated_at'] ?? null]) as $meta)
                        <span>{{ $meta }}</span>@if(! $loop->last)<span> &middot; </span>@endif
                    @endforeach
                </div>
            </div>

            <div class="semesters">
                @foreach($data['semesters'] ?? [] as $semester)
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
                                        @foreach($data['weekdays'] ?? [] as $weekday)
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
                                                            $courseLimitForCell = $courses->count() > $visibleCourseLimit ? max(1, $visibleCourseLimit - 1) : $visibleCourseLimit;
                                                            $shownCourses = $courses->take($courseLimitForCell);
                                                            $hiddenCourseCount = max(0, $courses->count() - $shownCourses->count());
                                                        @endphp

                                                        @foreach($shownCourses as $course)
                                                            <div class="course @if($hasDenseCourses) course--compact @endif">
                                                                <div class="course-label">
                                                                    {{ $course['label'] ?? '' }}@if(! empty($course['is_fu']))<span class="fu-badge">FU</span>@endif
                                                                    @if(! empty($course['student_course_badge']) && in_array($course['student_course_type'] ?? '', ['missing', 'additional'], true))
                                                                        <span class="student-course-badge student-course-badge--{{ $course['student_course_type'] }}">{{ $course['student_course_badge'] }}</span>
                                                                    @endif
                                                                </div>
                                                                @if(! $hasDenseCourses && ! empty($course['details']))
                                                                    <div class="course-details">{{ $course['details'] }}</div>
                                                                @endif
                                                            </div>
                                                        @endforeach

                                                        @if($hiddenCourseCount > 0)
                                                            <div class="course-more">
                                                                +{{ $hiddenCourseCount }} {{ $hiddenCourseCount === 1 ? 'weiterer Termin' : 'weitere Termine' }}
                                                            </div>
                                                        @endif

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

    @if($courseDirectory->isNotEmpty())
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
                        <th class="col-directory-details">Details</th>
                        <th class="col-directory-slots">Termine</th>
                        <th class="col-directory-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($courseDirectory as $entry)
                        <tr>
                            <td class="cell-label">{{ $entry['label'] }}@if(! empty($entry['is_fu']))<span class="fu-badge">FU</span>@endif</td>
                            <td class="cell-details">{{ $entry['details'] }}</td>
                            <td>{{ $entry['slots'] }}</td>
                            <td style="text-align: center">
                                <span class="status-dot status-dot--{{ $entry['status'] }}"></span>
                            </td>
                        </tr>
                        @if(!empty($entry['dates']))
                            <tr class="directory-dates-row">
                                <td></td>
                                <td colspan="3" class="directory-dates-cell">
                                    @foreach($entry['dates'] as $date)
                                        <span class="directory-date">{{ $date }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($courseOverviewSemesters->isNotEmpty())
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
                            <th class="col-details">Details</th>
                            <th class="col-status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($slots as $slot)
                            <tr>
                                <td class="cell-weekday">{{ $slot['weekday'] }}</td>
                                <td class="cell-hour">{{ $slot['hour_label'] ?? (($slot['hour'] ?? '') . '.') }}</td>
                                <td class="cell-time">{{ $slot['time'] }}</td>
                                <td class="cell-label">{{ $slot['label'] }}@if(! empty($slot['is_fu']))<span class="fu-badge">FU</span>@endif</td>
                                <td class="cell-details">{{ $slot['details'] }}</td>
                                <td>
                                    @if($slot['status'] !== 'empty')
                                        <span class="status-dot status-dot--{{ $slot['status'] }}" title="{{ $slot['status'] }}"></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    @endif
</body>
</html>
