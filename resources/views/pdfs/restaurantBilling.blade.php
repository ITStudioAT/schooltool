<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $billing['title'] }} - {{ $billing['period_label'] }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 8mm 9mm;
            size: A4 portrait;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 10.5px;
            line-height: 1.25;
        }

        .page {
            width: 100%;
        }

        .header {
            margin-bottom: 10px;
            padding: 8px 10px;
            border: 1px solid #d6dce3;
            border-radius: 8px;
            background: #eef6ff;
        }

        .eyebrow {
            margin-bottom: 3px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #1d4ed8;
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
            color: #475569;
        }

        .meta-row strong {
            color: #111827;
        }

        .summary-badge {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 10px;
            font-weight: 700;
        }

        .billing-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .billing-table th,
        .billing-table td {
            padding: 7px 8px;
            border: 1px solid #d8dee5;
            text-align: left;
            vertical-align: top;
        }

        .billing-table th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .billing-table tbody tr:nth-child(even) td {
            background: #f8fbff;
        }

        .billing-table__summary {
            width: 42%;
        }

        .billing-table__count,
        .billing-table__total {
            text-align: right;
            white-space: nowrap;
            font-weight: 700;
            color: #111827;
        }

        .billing-table__total-note {
            display: block;
            margin-top: 2px;
            font-size: 8px;
            font-weight: 400;
            color: #64748b;
        }

        .billing-table__summary-line {
            margin-bottom: 2px;
        }

        .billing-table__summary-line:last-child {
            margin-bottom: 0;
        }

        .billing-table tfoot td {
            background: #f8fafc;
            border-top: 2px solid #94a3b8;
            font-weight: 700;
            color: #0f172a;
        }

        .billing-table__overall-label {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="header">
            <div class="eyebrow">Restaurant Abrechnung</div>
            <h1 class="title">{{ $billing['title'] }} {{ $billing['period_label'] }}</h1>
            <div class="meta-row"><strong>Zeitraum:</strong> {{ $billing['range_label'] }}</div>
            @if($billing['school_name'] !== '')
                <div class="meta-row"><strong>Schule:</strong> {{ $billing['school_name'] }}</div>
            @endif
            <div class="meta-row"><strong>Erstellt am:</strong> {{ $billing['created_at'] }}</div>
            @if($billing['is_preview'] ?? false)
                <div class="meta-row"><strong>Status:</strong> Vorschau, nicht endgültig</div>
            @endif
            <div class="summary-badge">{{ $billing['bookings_count'] }} Menübestellung(en)</div>
        </section>

        <table class="billing-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Kunde</th>
                    <th class="billing-table__summary">Preisstaffel</th>
                    <th style="width: 12%;" class="billing-table__count">Menge</th>
                    <th style="width: 14%;" class="billing-table__total">Summe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['user_name'] }}</td>
                        <td class="billing-table__summary">
                            @foreach($row['price_lines'] as $priceLine)
                                <div class="billing-table__summary-line">{{ $priceLine['quantity'] }} x {{ $priceLine['price_label'] }} = {{ $priceLine['line_total_label'] }}</div>
                            @endforeach
                        </td>
                        <td class="billing-table__count">{{ $row['total_quantity'] }}</td>
                        <td class="billing-table__total">
                            {{ $row['total_amount_label'] }}
                            @if($billing['is_preview'] ?? false)
                                <span class="billing-table__total-note">nicht endgültig</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="billing-table__overall-label">Gesamtsumme aller Kunden</td>
                    <td class="billing-table__count">{{ $overallQuantity }}</td>
                    <td class="billing-table__total">
                        {{ $overallTotalLabel }}
                        @if($billing['is_preview'] ?? false)
                            <span class="billing-table__total-note">nicht endgültig</span>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
