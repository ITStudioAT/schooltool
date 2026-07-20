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
            font-size: 9pt;
            font-weight: 400;
            line-height: 1.35;
            margin: 0;
        }

        .header {
            margin-bottom: 10px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #4f46e5;
            border-radius: 6px;
            background: #f8fafc;
        }

        .header-label {
            font-size: 7.5pt;
            font-weight: 700;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 2px;
        }

        h1 {
            font-size: 18pt;
            font-weight: 700;
            line-height: 1.12;
            margin: 0 0 3px;
            color: #0f172a;
        }

        .header-description {
            font-size: 9pt;
            color: #475569;
            margin: 0 0 6px;
        }

        .summary {
            display: table;
            width: 100%;
            margin: 7px 0 6px;
            border-spacing: 4px 0;
            table-layout: fixed;
        }

        .summary-item {
            display: table-cell;
            padding: 4px 6px;
            border: 1px solid #dbe3ee;
            border-radius: 4px;
            background: #ffffff;
        }

        .summary-value {
            display: block;
            color: #0f172a;
            font-size: 11.5pt;
            font-weight: 700;
            line-height: 1;
        }

        .summary-label {
            display: block;
            margin-top: 1px;
            color: #64748b;
            font-size: 7.2pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .meta-info {
            font-size: 7.8pt;
            color: #64748b;
            margin: 0;
        }

        .topic {
            margin-bottom: 6px;
            border: 1px solid #d8e0eb;
            border-radius: 5px;
            overflow: hidden;
            break-inside: avoid-page;
            page-break-inside: avoid;
        }

        .topic-header {
            padding: 5px 8px;
            border-bottom: 1px solid #d8e0eb;
            background: #eef2ff;
        }

        .topic-title {
            font-weight: 700;
            font-size: 10pt;
            color: #1e1b4b;
        }

        .topic-number {
            display: inline-block;
            min-width: 22px;
            margin-right: 4px;
            color: #4f46e5;
            font-size: 8.2pt;
            font-weight: 700;
        }

        .units {
            padding: 1px 9px 2px;
        }

        .unit {
            padding: 3px 1px;
            border-bottom: 1px solid #edf1f6;
            color: #334155;
            font-size: 8.7pt;
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
            min-width: 22px;
            color: #94a3b8;
            font-size: 8pt;
        }

        .exam-badge {
            font-family: {!! $pdfFontFamily !!} !important;
            background: #fef2f2;
            color: #991b1b;
            font-size: 6.8pt;
            padding: 1px 4px;
            border-radius: 3px;
            font-weight: 700;
            white-space: nowrap;
        }

        .empty-state {
            padding: 18px;
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
