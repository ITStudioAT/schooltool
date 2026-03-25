<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Leistungen</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.45;
            margin: 28px;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-top: 22px;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .report-subtitle {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .report-table {
            margin-top: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            vertical-align: top;
            text-align: left;
        }

        td {
            font-size: 11px;
        }

        th {
            background: #e2e8f0;
            font-size: 11px;
        }

        .empty-box {
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            color: #64748b;
            padding: 10px 12px;
        }

        .muted {
            color: #64748b;
        }
    </style>
</head>
<body>
    @php($reports = $reports ?? [isset($report) ? $report : null])
    @foreach ($reports as $report)
        @if (! $report)
            @continue
        @endif
        <div class="report-header">
            <p class="report-subtitle">{{ $report['course_title'] }} · {{ $report['student_name'] }} · {{ $report['school_name'] ?: '-' }} · {{ $report['schoolyear_name'] ?: '-' }}</p>
        </div>

        @php($entryRows = collect())
        @for ($semester = 1; $semester <= $report['semester_count']; $semester++)
            @php(
                $entryRows = $entryRows->merge(
                    $report['entries_by_semester']->get($semester, collect())->map(
                        fn ($entry) => array_merge($entry, ['semester_label' => (string) $semester])
                    )
                )
            )
        @endfor

        <div class="report-table">
            @if ($entryRows->isEmpty())
                <div class="empty-box">Keine Leistungen vorhanden.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th style="width: 4%;">S</th>
                            <th style="width: 9%;">Datum</th>
                            <th style="width: 22%;">Typ</th>
                            <th style="width: 24%;">Arbeit</th>
                            <th style="width: 8%;">Note</th>
                            <th>Beschreibung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entryRows as $entry)
                            <tr>
                                <td>{{ $entry['semester_label'] }}</td>
                                <td>{{ $entry['date'] ?: '-' }}</td>
                                <td>{{ $entry['type_label'] ?: '-' }}</td>
                                <td>{{ $entry['work_title'] ?: '-' }}</td>
                                <td>{{ $entry['grade'] !== '' ? $entry['grade'] : 'NA' }}</td>
                                <td>{{ $entry['description'] ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
