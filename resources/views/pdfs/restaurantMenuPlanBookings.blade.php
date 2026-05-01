<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>{{ $plan['title'] }} - Bestellungen</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 10mm 10mm;
            size: A4 portrait;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 13px;
            line-height: 1.45;
        }

        .print-page {
            width: 100%;
            page-break-after: always;
        }

        .print-page:last-child {
            page-break-after: auto;
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
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9a3412;
        }

        .title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #111827;
        }

        .meta {
            margin-top: 5px;
            color: #4b5563;
        }

        .day-label {
            margin-top: 10px;
            font-size: 32px;
            font-weight: 800;
            line-height: 1.1;
            color: #111827;
        }

        .day-label__prefix {
            color: #9a3412;
        }

        .summary {
            margin-bottom: 10px;
            color: #475569;
            font-weight: 700;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
        }

        .booking-table th,
        .booking-table td {
            padding: 8px 9px;
            border: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        .booking-table .col-spacer {
            width: 1cm;
            padding: 0;
        }

        .booking-table .col-customer {
            padding-left: 12px;
        }

        .booking-table .col-time {
            width: 22mm;
            white-space: nowrap;
        }

        .booking-table th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .booking-table td {
            color: #334155;
        }

        .booking-table tr:nth-child(even) td {
            background: #fffaf5;
        }
    </style>
</head>

<body>
    @foreach($pages as $page)
    <div class="print-page">
        <section class="header">
            <div class="eyebrow">Restaurant Bestellungen</div>
            <h1 class="title">{{ $plan['title'] }}</h1>
            <div class="day-label"><span class="day-label__prefix">Tag:</span> {{ $page['weekday_label'] }}, {{ $page['date_label'] }}</div>
            @if($plan['school_name'] !== '')
            <div class="meta"><strong>Schule:</strong> {{ $plan['school_name'] }}</div>
            @endif
            <div class="meta"><strong>Erstellt am:</strong> {{ $plan['generated_at'] }}</div>
        </section>

        <div class="summary">{{ $page['summary_label'] ?? count($page['rows']).' Bestellung(en)' }}</div>

        <table class="booking-table">
            <thead>
                <tr>
                    <th class="col-spacer" style="width:1cm;padding:0;" aria-hidden="true"></th>
                    <th class="col-customer">Kunde</th>
                    <th>Men&uuml;</th>
                    <th class="col-time">Uhrzeit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($page['rows'] as $row)
                <tr>
                    <td class="col-spacer" style="width:1cm;padding:0;"></td>
                    <td class="col-customer">{{ $row['customer_name'] }}</td>
                    <td>{{ $row['menu_title'] }}</td>
                    <td class="col-time">{{ $row['time_label'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach
</body>

</html>
