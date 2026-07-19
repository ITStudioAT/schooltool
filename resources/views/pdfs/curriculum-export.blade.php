<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $curriculum->title }}</title>
    <style>
        {!! $embeddedFontCss !!}

        * {
            font-family: {!! $pdfFontFamily !!} !important;
            box-sizing: border-box;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            font-family: {!! $pdfFontFamily !!};
            color: #172033;
            font-size: 10pt;
            font-weight: 400;
            line-height: 1.5;
            margin: 0;
        }

        .header {
            margin-bottom: 18px;
            padding: 16px 18px;
            border: 1px solid #cbd5e1;
            border-left: 5px solid #4f46e5;
            border-radius: 8px;
            background: #f8fafc;
        }

        .header-label {
            font-size: 8.5pt;
            font-weight: 700;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin: 0 0 5px;
        }

        h1 {
            font-size: 21pt;
            font-weight: 700;
            line-height: 1.18;
            margin: 0 0 6px;
            color: #0f172a;
        }

        .header-description {
            font-size: 10pt;
            color: #475569;
            margin: 0 0 10px;
        }

        .summary {
            display: table;
            width: 100%;
            margin: 12px 0 10px;
            border-spacing: 6px 0;
            table-layout: fixed;
        }

        .summary-item {
            display: table-cell;
            padding: 7px 9px;
            border: 1px solid #dbe3ee;
            border-radius: 5px;
            background: #ffffff;
        }

        .summary-value {
            display: block;
            color: #0f172a;
            font-size: 13pt;
            font-weight: 700;
            line-height: 1.1;
        }

        .summary-label {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .meta-info {
            font-size: 8.5pt;
            color: #64748b;
            margin: 0;
        }

        .topic {
            margin-bottom: 11px;
            border: 1px solid #d8e0eb;
            border-radius: 6px;
            overflow: hidden;
            break-inside: avoid-page;
            page-break-inside: avoid;
        }

        .topic-header {
            padding: 8px 11px;
            border-bottom: 1px solid #d8e0eb;
            background: #eef2ff;
        }

        .topic-title {
            font-weight: 700;
            font-size: 11pt;
            color: #1e1b4b;
        }

        .topic-number {
            display: inline-block;
            min-width: 24px;
            margin-right: 5px;
            color: #4f46e5;
            font-size: 9pt;
            font-weight: 700;
        }

        .units {
            padding: 4px 12px 6px;
        }

        .unit {
            padding: 5px 2px;
            border-bottom: 1px solid #edf1f6;
            color: #334155;
            font-size: 9.5pt;
        }

        .unit:last-child {
            border-bottom: none;
        }

        .unit-exam {
            color: #991b1b;
            font-weight: 700;
        }

        .unit-number {
            display: inline-block;
            min-width: 24px;
            color: #94a3b8;
            font-size: 8.5pt;
        }

        .exam-badge {
            font-family: {!! $pdfFontFamily !!} !important;
            background: #fef2f2;
            color: #991b1b;
            font-size: 7.5pt;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: 700;
            white-space: nowrap;
        }

        .empty-state {
            padding: 28px;
            border: 1px dashed #cbd5e1;
            border-radius: 7px;
            color: #64748b;
            text-align: center;
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
        <div class="summary">
            <div class="summary-item">
                <span class="summary-value">{{ count($topics) }}</span>
                <span class="summary-label">Themen</span>
            </div>
            <div class="summary-item">
                <span class="summary-value">{{ $unitCount }}</span>
                <span class="summary-label">Einheiten</span>
            </div>
            <div class="summary-item">
                <span class="summary-value">{{ $assessmentCount }}</span>
                <span class="summary-label">Leistungsfeststellungen</span>
            </div>
        </div>
        <p class="meta-info">
            @if($userName)Lehrperson: {{ $userName }}@endif
            @if($schoolName) &middot; {{ $schoolName }}@endif
            &middot; Druckdatum: {{ $printDate }} Uhr
        </p>
    </div>

    @forelse($topics as $index => $topic)
        <div class="topic">
            <div class="topic-header">
                <div class="topic-title">
                    <span class="topic-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    {{ $topic['title'] ?? '' }}
                </div>
            </div>

            @if(! empty($topic['units']))
                <div class="units">
                    @foreach($topic['units'] as $unitIndex => $unit)
                        <div class="unit {{ !empty($unit['is_exam']) ? 'unit-exam' : '' }}">
                            <span class="unit-number">{{ $index + 1 }}.{{ $unitIndex + 1 }}</span>
                            {{ $unit['title'] ?? '' }}
                            @if(! empty($unit['is_exam']))
                                <span class="exam-badge">Leistungsfeststellung</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="empty-state">Dieses Curriculum enthält noch keine Themen.</div>
    @endforelse
</body>
</html>
