<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $plan['title'] }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 6mm 6mm;
            size: A4 landscape;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 11px;
            line-height: 1.25;
        }

        .page {
            width: 100%;
        }

        .header {
            margin-bottom: 5px;
            padding: 6px 8px;
            border: 1px solid #d6dce3;
            border-radius: 8px;
            background: #fff8e8;
        }

        .eyebrow {
            margin-bottom: 3px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #92400e;
        }

        .title {
            margin: 0 0 3px;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }

        .meta-row {
            margin-top: 2px;
            font-size: 12px;
            color: #4b5563;
        }

        .meta-row strong {
            color: #111827;
        }

        .week-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2px;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .week-day {
            vertical-align: top;
            border: 1px solid #d8dee5;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
        }

        .week-day__header {
            padding: 4px 5px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .week-day__title {
            margin: 0;
            font-size: 11.5px;
            font-weight: 700;
            color: #111827;
        }

        .week-day__date {
            margin-top: 1px;
            font-size: 9.5px;
            color: #6b7280;
        }

        .week-day__body {
            padding: 4px 5px 5px;
        }

        .week-day__note {
            font-size: 10px;
            font-weight: 600;
            color: #475569;
        }

        .week-day__note.is-free {
            color: #15803d;
        }

        .entry + .entry {
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #d1d5db;
        }

        .entry-head {
            margin-bottom: 2px;
        }

        .entry-title {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
        }

        .entry-price {
            margin-top: 1px;
            font-size: 10px;
            font-weight: 700;
            color: #92400e;
        }

        .entry-subline {
            margin-top: 1px;
            font-size: 9.5px;
            color: #4b5563;
        }

        .entry-subline--comment {
            color: #334155;
        }

        .foods {
            margin-top: 3px;
        }

        .food + .food {
            margin-top: 2px;
        }

        .food {
            padding: 3px 4px;
            border-radius: 6px;
            background: #fffbeb;
            border: 1px solid #fde68a;
        }

        .food-course {
            margin-bottom: 1px;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #b45309;
        }

        .food-title {
            font-size: 10px;
            font-weight: 700;
            color: #111827;
        }

        .food-meta {
            margin-top: 1px;
            font-size: 9px;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="header">
            <div class="eyebrow">Restaurant</div>
            <h1 class="title">{{ $plan['title'] }}</h1>
            <div class="meta-row"><strong>Zeitraum:</strong> {{ $plan['range_label'] }}</div>
            @if($plan['school_name'] !== '')
                <div class="meta-row"><strong>Schule:</strong> {{ $plan['school_name'] }}</div>
            @endif
            <div class="meta-row"><strong>Erstellt am:</strong> {{ $plan['generated_at'] }}</div>
        </section>

        @foreach(array_chunk($days, 7) as $weekChunk)
            <table class="week-table">
                <tr>
                    @foreach($weekChunk as $day)
                        <td class="week-day">
                            <div class="week-day__header">
                                <div class="week-day__title">{{ $day['weekday_label'] }}</div>
                                <div class="week-day__date">{{ $day['date_label'] }}</div>
                            </div>

                            <div class="week-day__body">
                                @if($day['is_free_day'])
                                    <div class="week-day__note is-free">Freier Tag</div>
                                @elseif(count($day['entries']) === 0)
                                    <div class="week-day__note">Kein Men&uuml; eingetragen.</div>
                                @else
                                    @foreach($day['entries'] as $entry)
                                        <div class="entry">
                                            <div class="entry-head">
                                                <div class="entry-title">{{ $entry['menu_title'] }}</div>
                                                @if($entry['price'])
                                                    <div class="entry-price">{{ $entry['price'] }}</div>
                                                @endif
                                            </div>

                                            @if($entry['base_price'])
                                                <div class="entry-subline">Basispreis: {{ $entry['base_price'] }}</div>
                                            @endif

                                            @if(count($entry['eating_times']) > 0)
                                                <div class="entry-subline">{{ implode(', ', $entry['eating_times']) }}</div>
                                            @endif

                                            @if($entry['comments'])
                                                <div class="entry-subline entry-subline--comment">{{ $entry['comments'] }}</div>
                                            @endif

                                            @if(count($entry['foods']) > 0)
                                                <div class="foods">
                                                    @foreach($entry['foods'] as $food)
                                                        <div class="food">
                                                            <div class="food-course">{{ $food['course_label'] }}</div>
                                                            <div class="food-title">{{ $food['title'] }}</div>
                                                            @if($food['category'])
                                                                <div class="food-meta">{{ $food['category'] }}</div>
                                                            @endif
                                                            @if($food['description'])
                                                                <div class="food-meta">{{ $food['description'] }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endforeach
    </div>
</body>
</html>
