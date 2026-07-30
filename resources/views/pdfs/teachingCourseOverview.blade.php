<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Kursübersicht · {{ $course_title }}</title>
    <style>
        @page {
            margin: 23mm 5mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        html {
            -webkit-print-color-adjust: exact;
        }

        body {
            color: #172033;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.05;
            margin: 0;
        }

        .pdf-header {
            left: 0;
            position: fixed;
            right: 0;
            top: -19mm;
        }

        .pdf-footer {
            bottom: -8mm;
            left: 0;
            position: fixed;
            right: 0;
        }

        .pageNumber::before {
            content: counter(page);
        }

        .overview-section--break {
            page-break-after: always;
        }

        .section-heading {
            color: #63718a;
            font-size: 9pt;
            margin: 0 0 0.8mm;
            text-align: right;
            text-transform: uppercase;
        }

        .overview-table {
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
            width: 100%;
        }

        .overview-table th,
        .overview-table td {
            border-bottom: 0.25mm solid #dbe3ef;
            border-right: 0.25mm solid #dbe3ef;
            padding: 0.45mm 0.5mm;
            vertical-align: top;
        }

        .overview-table tr > *:first-child {
            border-left: 0.25mm solid #dbe3ef;
        }

        .overview-table thead tr:first-child > * {
            border-top: 0.25mm solid #dbe3ef;
        }

        .student-heading {
            background: #182a52;
            border-top-left-radius: 1.5mm;
            color: #ffffff;
            font-size: 9pt;
            padding: 1mm 0.7mm !important;
        }

        .student-heading-count {
            color: #c7d7ff;
            display: block;
            font-size: 9pt;
            font-weight: 400;
            margin-top: 0.15mm;
        }

        .date-heading {
            background: #24447f;
            color: #ffffff;
            padding: 0.8mm 0.45mm !important;
        }

        .overview-table thead tr:first-child > *:last-child {
            border-top-right-radius: 1.5mm;
        }

        .date-weekday {
            color: #bcd1ff;
            display: block;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .date-value {
            display: block;
            font-size: 9pt;
            margin-top: 0.1mm;
        }

        .date-hours {
            color: #dce7ff;
            display: block;
            font-size: 9pt;
            font-weight: 400;
            margin-top: 0.1mm;
        }

        .context-label {
            background: #f3f6fb;
            color: #44536d;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .context-cell {
            background: #fbfcfe;
            color: #34425a;
            font-size: 9pt;
        }

        .context-cell--works {
            background: #f6f9ff;
            overflow: hidden;
            padding: 0.35mm 0 !important;
        }

        .context-cell--curriculum {
            background: #fbf9ff;
        }

        .work-lane {
            height: 5mm;
            overflow: hidden;
            position: relative;
        }

        .work-lane-label {
            color: #28436e;
            font-size: 9pt;
            height: 5mm;
            line-height: 1.05;
            overflow: hidden;
            padding: 0.55mm 0;
            text-transform: none;
            white-space: nowrap;
        }

        .work-lane-label-type {
            font-weight: 700;
        }

        .work-timeline-line {
            border-top: 0.45mm solid #2f6fed;
            left: -0.4mm;
            position: absolute;
            right: -0.4mm;
            top: 2.35mm;
        }

        .work-lane--start .work-timeline-line,
        .work-lane--continuation .work-timeline-line {
            left: 1.8mm;
        }

        .work-timeline-dot {
            background: #ffffff;
            border: 0.45mm solid #2f6fed;
            border-radius: 50%;
            height: 1.8mm;
            left: 0.55mm;
            position: absolute;
            top: 1.55mm;
            width: 1.8mm;
        }

        .work-timeline-arrow {
            border-bottom: 1.1mm solid transparent;
            border-left: 1.7mm solid #2f6fed;
            border-top: 1.1mm solid transparent;
            height: 0;
            position: absolute;
            right: 0.25mm;
            top: 1.4mm;
            width: 0;
        }

        .work-chip {
            background: #dce8ff;
            border: 0.2mm solid #9dbcf7;
            border-radius: 1.1mm;
            color: #173d7c;
            display: block;
            font-size: 9pt;
            height: 4.4mm;
            line-height: 3.6mm;
            margin: 0.3mm 0.35mm;
            overflow: hidden;
            padding: 0.2mm 0.45mm;
            white-space: nowrap;
        }

        .work-type {
            font-weight: 700;
        }

        .curriculum-item {
            border-left: 0.45mm solid #8a68d3;
            color: #50328d;
            display: block;
            margin-bottom: 0.35mm;
            padding-left: 0.65mm;
        }

        .curriculum-item:last-child {
            margin-bottom: 0;
        }

        .muted {
            color: #9aa5b7;
        }

        .student-cell {
            background: #f8faff;
            color: #1f2c43;
            font-size: 9pt;
            font-weight: 700;
            padding: 0.4mm 0.5mm !important;
        }

        .student-name-table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .overview-table .student-name-table td {
            background: transparent !important;
            border: 0 !important;
            padding: 0 !important;
        }

        .student-name-text {
            overflow: hidden;
            white-space: nowrap;
        }

        .overview-table .student-name-table .student-class-cell {
            text-align: right;
            width: 8mm;
        }

        .student-class {
            background: #e7eefc;
            border-radius: 0.8mm;
            color: #3f557a;
            display: inline-block;
            font-size: 9pt;
            font-weight: 700;
            margin-left: 0.25mm;
            padding: 0.05mm 0.3mm;
        }

        .entry-cell {
            background: #ffffff;
            padding: 0.3mm 0.4mm !important;
        }

        tbody tr:nth-child(even) .student-cell,
        tbody tr:nth-child(even) .entry-cell {
            background: #f9fbfe;
        }

        .entry-chip {
            background: #eef3fb;
            border-left: 0.45mm solid #4d72b8;
            border-radius: 0.6mm;
            color: #273b60;
            display: block;
            height: 4.1mm;
            margin-bottom: 0.2mm;
            overflow: hidden;
            padding: 0.25mm 0.35mm;
            white-space: nowrap;
        }

        .entry-chip:last-child {
            margin-bottom: 0;
        }

        .entry-type {
            font-weight: 700;
        }

        .entry-grade {
            background: #24447f;
            border-radius: 0.6mm;
            color: #ffffff;
            float: right;
            font-size: 9pt;
            font-weight: 700;
            margin-left: 0.25mm;
            padding: 0.05mm 0.3mm;
        }

        .entry-description {
            color: #66738a;
            display: inline;
            font-size: 9pt;
            margin-left: 0.4mm;
        }

        .absence-chip {
            background: #fff0ed;
            border: 0.2mm solid #ffc6bc;
            border-radius: 0.8mm;
            color: #a32d1d;
            display: inline-block;
            font-size: 9pt;
            font-weight: 700;
            margin-bottom: 0.15mm;
            padding: 0.05mm 0.3mm;
        }

        .empty-state {
            background: #f7f9fc;
            border: 0.25mm dashed #b9c5d8;
            border-radius: 1.2mm;
            color: #65738a;
            font-size: 9pt;
            padding: 8mm;
            text-align: center;
        }
    </style>
</head>
<body>
    @forelse($date_sections as $sectionIndex => $section)
        <section @class(['overview-section', 'overview-section--break' => ! $loop->last])>
            @if(count($date_sections) > 1)
                <p class="section-heading">
                    Termine {{ ($sectionIndex * 7) + 1 }}–{{ min(($sectionIndex + 1) * 7, $date_count) }}
                    von {{ $date_count }}
                </p>
            @endif

            <table class="overview-table">
                <thead>
                    <tr>
                        <th class="student-heading" style="width: 22%;">
                            Schüler:innen
                            <span class="student-heading-count">{{ $student_count }} im Kurs</span>
                        </th>
                        @foreach($section['dates'] as $date)
                            <th class="date-heading" style="width: {{ 78 / max(count($section['dates']), 1) }}%;">
                                <span class="date-weekday">{{ $date['weekday'] }}</span>
                                <span class="date-value">{{ $date['date_label'] }}</span>
                                @if($date['hours'] !== '')
                                    <span class="date-hours">{{ $date['hours'] }}. Std.</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="context-label">
                            @forelse($section['work_lanes'] as $workLane)
                                <div class="work-lane-label">
                                    <span class="work-lane-label-type">{{ $workLane['type'] }}</span>
                                    @if($workLane['title'] !== '')
                                        · {{ $workLane['title'] }}
                                    @endif
                                </div>
                            @empty
                                Arbeiten
                            @endforelse
                        </th>
                        @foreach($section['dates'] as $dateIndex => $date)
                            <td class="context-cell context-cell--works">
                                @forelse($section['work_lanes'] as $workLane)
                                    @php($workCell = $workLane['cells'][$dateIndex])
                                    <div @class([
                                        'work-lane',
                                        'work-lane--start' => $workCell['is_start'],
                                        'work-lane--continuation' => $workCell['is_continuation_start'],
                                    ])>
                                        @if($workCell['has_line'])
                                            <span class="work-timeline-line"></span>
                                        @endif
                                        @if($workCell['is_start'] || $workCell['is_continuation_start'])
                                            <span class="work-timeline-dot"></span>
                                        @endif
                                        @if($workCell['is_arrow'])
                                            <span class="work-timeline-arrow"></span>
                                        @endif
                                        @if($workCell['is_finish'])
                                            <span class="work-chip">
                                                <span class="work-type">{{ $workLane['type'] }}</span>
                                                @if($workLane['title'] !== '')
                                                    · {{ $workLane['title'] }}
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="muted">–</span>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="context-label">Curriculum</th>
                        @foreach($section['dates'] as $date)
                            <td class="context-cell context-cell--curriculum">
                                @forelse($date['curriculum'] as $curriculumItem)
                                    <span class="curriculum-item">{{ $curriculumItem }}</span>
                                @empty
                                    <span class="muted">–</span>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="context-label">Inhalt</th>
                        @foreach($section['dates'] as $date)
                            <td class="context-cell">
                                {{ $date['content'] !== '' ? $date['content'] : '–' }}
                            </td>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['students'] as $student)
                        <tr>
                            <td class="student-cell">
                                <table class="student-name-table">
                                    <tr>
                                        <td class="student-name-text">
                                            {{ $student['name'] !== '' ? $student['name'] : 'Schüler:in ohne Namen' }}
                                        </td>
                                        @if($student['class'] !== '')
                                            <td class="student-class-cell">
                                                <span class="student-class">{{ $student['class'] }}</span>
                                            </td>
                                        @endif
                                    </tr>
                                </table>
                            </td>
                            @foreach($student['cells'] as $cell)
                                <td class="entry-cell">
                                    @if($cell['is_absent'])
                                        <span class="absence-chip">Abwesend</span>
                                    @endif

                                    @forelse($cell['entries'] as $entry)
                                        <span class="entry-chip">
                                            @if($entry['grade'] !== '')
                                                <span class="entry-grade">{{ $entry['grade'] }}</span>
                                            @endif
                                            <span class="entry-type">{{ $entry['type'] !== '' ? $entry['type'] : 'Eintrag' }}</span>
                                            @if($entry['description'] !== '')
                                                <span class="entry-description">{{ $entry['description'] }}</span>
                                            @endif
                                        </span>
                                    @empty
                                        @if(! $cell['is_absent'])
                                            <span class="muted">–</span>
                                        @endif
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <div class="empty-state">
            Für den gewählten Zeitraum sind noch keine Termine vorhanden.
        </div>
    @endforelse
</body>
</html>
