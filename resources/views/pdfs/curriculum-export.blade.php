<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $curriculum->title }}</title>
    <style>
        {!! $embeddedFontCss !!}

        * {
            font-family: {!! $pdfFontFamily !!} !important;
        }

        body {
            font-family: {!! $pdfFontFamily !!};
            color: #1e293b;
            font-size: 10pt;
            line-height: 1.5;
            margin: 28px;
        }

        .header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }

        .header-label {
            font-size: 10pt;
            font-weight: 700;
            color: #6366f1;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0 0 4px;
        }

        h1 {
            font-size: 20pt;
            font-weight: 700;
            margin: 0 0 4px;
            color: #0f172a;
        }

        .header-description {
            font-size: 10pt;
            color: #475569;
            font-style: italic;
            margin: 0 0 8px;
        }

        .meta {
            font-size: 10pt;
            color: #64748b;
            margin: 0 0 4px;
        }

        .meta-info {
            font-size: 10pt;
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
            font-size: 11pt;
        }

        .units {
            padding: 6px 12px 8px 28px;
        }

        .unit {
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10pt;
        }

        .unit:last-child {
            border-bottom: none;
        }

        .unit-exam {
            color: #dc2626;
            font-weight: 600;
        }

        .exam-badge {
            font-family: {!! $pdfFontFamily !!} !important;
            background: #fef2f2;
            color: #dc2626;
            font-size: 10pt;
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
            {{ count($topics) }} Themen
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
            </div>

            @if (!empty($topic['units']))
                <div class="units">
                    @foreach ($topic['units'] as $unitIndex => $unit)
                        <div class="unit {{ !empty($unit['is_exam']) ? 'unit-exam' : '' }}">
                            {{ $unit['title'] ?? '' }}
                            @if (!empty($unit['is_exam']))
                                <span class="exam-badge">Prüfung</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
