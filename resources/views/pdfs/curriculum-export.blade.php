<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $curriculum->title }}</title>
    <style>
        * {
            font-family: Arial, Helvetica, sans-serif !important;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.5;
            margin: 28px;
        }

        .header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }

        .header-label {
            font-size: 9px;
            font-weight: 700;
            color: #6366f1;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0 0 4px;
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px;
            color: #0f172a;
        }

        .header-description {
            font-size: 10px;
            color: #475569;
            font-style: italic;
            margin: 0 0 8px;
        }

        .meta {
            font-size: 9px;
            color: #64748b;
            margin: 0 0 4px;
        }

        .meta-info {
            font-size: 8px;
            color: #94a3b8;
            margin: 0;
        }

        .topic {
            margin-bottom: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }

        .topic-header {
            background: #f1f5f9;
            padding: 8px 12px;
        }

        .topic-title {
            font-weight: 700;
            font-size: 11px;
        }

        .topic-assignment {
            color: #6366f1;
            font-size: 9px;
            font-weight: 600;
            margin-top: 2px;
        }

        .units {
            padding: 6px 12px 8px 28px;
        }

        .unit {
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10px;
        }

        .unit:last-child {
            border-bottom: none;
        }

        .unit-exam {
            color: #dc2626;
            font-weight: 600;
        }

        .unit-assignment {
            color: #6366f1;
            font-size: 8px;
            font-weight: 500;
            margin-top: 1px;
        }

        .exam-badge {
            background: #fef2f2;
            color: #dc2626;
            font-size: 8px;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="header-label">Curriculum</p>
        <h1>{{ $curriculum->title }}</h1>
        @if ($curriculum->description)
            <p class="header-description">{{ $curriculum->description }}</p>
        @endif
        <p class="meta">
            {{ $curriculum->semester_count ?? 2 }} Semester &middot;
            {{ count($topics) }} Themen
            @if ($freeWeeks > 0)
                &middot; {{ $freeWeeks }} freie Wochen
            @endif
        </p>
        <p class="meta-info">
            @if ($userName)Lehrperson: {{ $userName }}@endif
            @if ($schoolName) &middot; {{ $schoolName }}@endif
            &middot; Druckdatum: {{ $printDate }} Uhr
        </p>
    </div>

    @foreach ($topics as $index => $topic)
        <div class="topic">
            <div class="topic-header">
                <div class="topic-title">{{ $index + 1 }}. {{ $topic['title'] ?? '' }}</div>
                @if (!empty($assignmentLabels[$index]['dateRange']))
                    <div class="topic-assignment">{{ $assignmentLabels[$index]['dateRange'] }}</div>
                @elseif (!empty($assignmentLabels[$index]['assignment']))
                    <div class="topic-assignment">{{ $assignmentLabels[$index]['assignment'] }}</div>
                @endif
            </div>

            @if (!empty($topic['units']))
                <div class="units">
                    @foreach ($topic['units'] as $unitIndex => $unit)
                        <div class="unit {{ !empty($unit['is_exam']) ? 'unit-exam' : '' }}">
                            {{ $unit['title'] ?? '' }}
                            @if (!empty($unit['is_exam']))
                                <span class="exam-badge">Prüfung</span>
                            @endif
                            @if (!empty($assignmentLabels[$index]['units'][$unitIndex]))
                                <div class="unit-assignment">{{ $assignmentLabels[$index]['units'][$unitIndex] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
