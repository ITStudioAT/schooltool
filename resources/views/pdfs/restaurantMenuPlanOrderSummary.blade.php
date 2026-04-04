<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $plan['title'] }} - Menüsummen</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 8mm 9mm;
            size: A4 landscape;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 10.5px;
            line-height: 1.2;
        }

        .page {
            width: 100%;
        }

        .header {
            margin-bottom: 8px;
            padding: 8px 10px;
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
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .meta-row {
            margin-top: 2px;
            font-size: 11px;
            color: #4b5563;
        }

        .meta-row strong {
            color: #111827;
        }

        .summary-badge {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #fed7aa;
            color: #9a3412;
            font-size: 10px;
            font-weight: 700;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .summary-table th,
        .summary-table td {
            padding: 6px 7px;
            border: 1px solid #d8dee5;
            text-align: left;
            vertical-align: top;
        }

        .summary-table th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-table td {
            color: #334155;
        }

        .summary-table tbody tr:nth-child(even) td {
            background: #fffaf5;
        }

        .summary-table tfoot td {
            background: #f8fafc;
            border-top: 2px solid #94a3b8;
            font-weight: 700;
            color: #0f172a;
        }

        th.summary-table__count,
        td.summary-table__count {
            width: 12%;
            text-align: right;
            font-weight: 700;
            color: #111827;
        }

        .summary-table__total-label {
            text-align: right;
        }

        .summary-table__menu.is-placeholder {
            color: #64748b;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="header">
            <div class="eyebrow">Restaurant Menüsummen</div>
            <h1 class="title">{{ $plan['title'] }}</h1>
            <div class="meta-row"><strong>Zeitraum:</strong> {{ $plan['range_label'] }}</div>
            @if($plan['school_name'] !== '')
                <div class="meta-row"><strong>Schule:</strong> {{ $plan['school_name'] }}</div>
            @endif
            <div class="meta-row"><strong>Erstellt am:</strong> {{ $plan['generated_at'] }}</div>
            <div class="summary-badge">Gesamtbestellungen: {{ $totalOrders }}</div>
        </section>

        <table class="summary-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Tag</th>
                    <th style="width: 15%;">Datum</th>
                    <th style="width: 55%;">Menü</th>
                    <th class="summary-table__count">Anzahl</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['weekday_label'] }}</td>
                        <td>{{ $row['date_label'] }}</td>
                        <td class="summary-table__menu{{ $row['is_placeholder'] ? ' is-placeholder' : '' }}">{{ $row['menu_title'] }}</td>
                        <td class="summary-table__count">{{ $row['orders_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="summary-table__total-label">Summe aller Bestellungen</td>
                    <td class="summary-table__count">{{ $totalOrders }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
