<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Restaurant Übersicht</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 12mm 10mm;
            size: A4 portrait;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 10px;
            line-height: 1.45;
        }

        .page {
            width: 100%;
        }

        .header {
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff7ed;
        }

        .eyebrow {
            margin-bottom: 4px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9a3412;
        }

        .title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .meta {
            margin-top: 6px;
            color: #4b5563;
        }

        .section {
            margin-top: 16px;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }

        .section-subtitle {
            margin: 0 0 10px;
            font-size: 11px;
            color: #6b7280;
        }

        .booking-day {
            margin-bottom: 10px;
            padding: 10px 12px;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            background: #fffaf3;
            page-break-inside: avoid;
        }

        .booking-day-title {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 700;
            color: #9a3412;
        }

        .booking-menu + .booking-menu {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #fdba74;
        }

        .booking-menu-title {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
        }

        .booking-row {
            margin-top: 3px;
            color: #4b5563;
        }

        .plan {
            margin-bottom: 12px;
            padding: 10px 12px;
            border: 1px solid #dbe3ea;
            border-radius: 10px;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .plan-title {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            color: #111827;
        }

        .plan-range {
            margin-top: 2px;
            color: #4b5563;
        }

        .plan-days {
            margin-top: 8px;
        }

        .plan-day + .plan-day {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #d1d5db;
        }

        .plan-day-title {
            font-size: 10.5px;
            font-weight: 700;
            color: #92400e;
        }

        .plan-entry {
            margin-top: 4px;
            padding-left: 8px;
        }

        .plan-entry-title {
            font-weight: 700;
            color: #111827;
        }

        .plan-entry-meta {
            margin-top: 2px;
            color: #4b5563;
        }

        .empty {
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="header">
            <div class="eyebrow">Restaurant Übersicht</div>
            <h1 class="title">{{ $school['name'] !== '' ? $school['name'] : 'Restaurant' }}</h1>
            <div class="meta"><strong>Nutzer:</strong> {{ $user['name'] }}@if($user['email'] !== '') ({{ $user['email'] }})@endif</div>
            <div class="meta"><strong>Erstellt am:</strong> {{ $school['generated_at'] }}</div>
        </section>

        <section class="section">
            <h2 class="section-title">Bereits gebucht</h2>
            <p class="section-subtitle">Ihre Menüs</p>

            @if(count($bookings) === 0)
                <div class="empty">Keine aktuellen Buchungen vorhanden.</div>
            @else
                @foreach($bookings as $bookingDay)
                    <div class="booking-day">
                        <div class="booking-day-title">{{ $bookingDay['weekday_label'] }}, {{ $bookingDay['date_label'] }}</div>

                        @foreach($bookingDay['menu_groups'] as $menuGroup)
                            <div class="booking-menu">
                                <div class="booking-menu-title">{{ $menuGroup['menu_title'] }}</div>

                                @foreach($menuGroup['bookings'] as $booking)
                                    <div class="booking-row">
                                        <strong>{{ $booking['quantity'] }}x</strong>
                                        @if($booking['eating_time'])
                                            um {{ $booking['eating_time'] }} Uhr
                                        @endif
                                        @if($booking['recipients'] !== '')
                                            · {{ $booking['recipients'] }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @endif
        </section>

        <section class="section">
            <h2 class="section-title">Menüplan</h2>

            @if(count($plans) === 0)
                <div class="empty">Kein sichtbarer Menüplan vorhanden.</div>
            @else
                @foreach($plans as $plan)
                    <div class="plan">
                        <h3 class="plan-title">{{ $plan['title'] }}</h3>
                        <div class="plan-range">{{ $plan['range_label'] }}</div>

                        <div class="plan-days">
                            @foreach($plan['days'] as $day)
                                <div class="plan-day">
                                    <div class="plan-day-title">{{ $day['weekday_label'] }}, {{ $day['date_label'] }}</div>

                                    @if(count($day['entries']) === 0)
                                        <div class="plan-entry">
                                            <div class="plan-entry-meta">Kein Menü eingetragen.</div>
                                        </div>
                                    @else
                                        @foreach($day['entries'] as $entry)
                                            <div class="plan-entry">
                                                <div class="plan-entry-title">
                                                    {{ $entry['menu_title'] }}
                                                    @if($entry['price'])
                                                        · {{ $entry['price'] }}
                                                    @endif
                                                </div>
                                                @if(count($entry['eating_times']) > 0)
                                                    <div class="plan-entry-meta">{{ implode(', ', $entry['eating_times']) }}</div>
                                                @endif
                                                @if($entry['comments'])
                                                    <div class="plan-entry-meta">{{ $entry['comments'] }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </section>
    </div>
</body>
</html>
