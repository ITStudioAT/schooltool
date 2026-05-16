<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Noten</title>
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
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .report-subtitle {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
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

        .text-center {
            text-align: center;
        }

        .muted {
            color: #64748b;
        }

        .empty-box {
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            color: #64748b;
            padding: 10px 12px;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <p class="report-subtitle">{{ $course_title }} · {{ $school_name ?: '-' }} · Schuljahr {{ $schoolyear_name ?: '-' }} · {{ $generated_at }}</p>
    </div>

    @if (empty($students))
        <div class="empty-box">Keine Schüler:innen vorhanden.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th style="width: 8%;">Klasse</th>
                    @if (in_array(1, $semesters))
                        <th class="text-center" style="width: 10%;">Note Sem. 1</th>
                    @endif
                    @if (in_array(2, $semesters))
                        <th class="text-center" style="width: 10%;">Note Sem. 2</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($students as $index => $student)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $student['name'] ?: '-' }}</td>
                        <td class="muted">{{ $student['email'] ?: '-' }}</td>
                        <td>{{ $student['class'] ?: '-' }}</td>
                        @if (in_array(1, $semesters))
                            <td class="text-center">{{ $student['sem_1_grade'] ?? '-' }}</td>
                        @endif
                        @if (in_array(2, $semesters))
                            <td class="text-center">{{ $student['sem_2_grade'] ?? '-' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
