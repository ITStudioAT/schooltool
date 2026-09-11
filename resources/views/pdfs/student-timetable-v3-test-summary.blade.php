<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Testzusammenfassung Stundenplan V3</title>
    @php
        $isSuccessful = ($data['failed_count'] ?? 0) === 0 && ($data['invalid_count'] ?? 0) === 0;
        $detailRows = collect($data['failed_students'] ?? [])
            ->map(fn (array $student): array => [...$student, 'type' => 'Fehlgeschlagen'])
            ->concat(collect($data['invalid_students'] ?? [])
                ->map(fn (array $student): array => [...$student, 'type' => 'Falsche Daten']))
            ->values();
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            line-height: 1.35;
        }

        .summary-page {
            box-sizing: border-box;
            padding: 8mm 10mm;
            border-top: 3mm solid #c2410c;
            background: #ffffff;
        }

        .kicker {
            margin: 0 0 2mm;
            color: #c2410c;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 3mm;
            color: #0f172a;
            font-size: 24pt;
            line-height: 1.1;
        }

        .meta {
            margin: 0 0 6mm;
            color: #64748b;
            font-size: 8.5pt;
        }

        .meta span + span::before {
            margin: 0 2mm;
            content: "•";
        }

        .status-banner {
            margin-bottom: 5mm;
            padding: 3.5mm 4mm;
            border: 0.35mm solid {{ $isSuccessful ? '#86efac' : '#fecaca' }};
            border-radius: 2mm;
            background: {{ $isSuccessful ? '#f0fdf4' : '#fef2f2' }};
            color: {{ $isSuccessful ? '#166534' : '#991b1b' }};
            font-size: 11pt;
            font-weight: 700;
        }

        .summary-counts {
            width: 100%;
            margin: 0 0 6mm;
            border-spacing: 2.5mm 0;
            table-layout: fixed;
        }

        .summary-count {
            padding: 3mm 3.5mm;
            border: 0.35mm solid #bfdbfe;
            border-radius: 2mm;
            background: #eff6ff;
            vertical-align: top;
        }

        .summary-count--success {
            border-color: #bbf7d0;
            background: #f0fdf4;
        }

        .summary-count--error {
            border-color: #fecaca;
            background: #fef2f2;
        }

        .summary-label {
            display: block;
            margin-bottom: 1mm;
            color: #475569;
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .summary-value {
            display: block;
            color: #1e3a8a;
            font-size: 20pt;
            font-weight: 800;
            line-height: 1;
        }

        .summary-count--success .summary-value {
            color: #15803d;
        }

        .summary-count--error .summary-value {
            color: #b91c1c;
        }

        h2 {
            margin: 0 0 2mm;
            color: #1e3a8a;
            font-size: 12pt;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .details-table thead {
            display: table-header-group;
        }

        .details-table tr {
            page-break-inside: avoid;
        }

        .details-table th,
        .details-table td {
            padding: 2.2mm 2.5mm;
            border: 0.25mm solid #cbd5e1;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .details-table th {
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 7.5pt;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .details-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .col-status {
            width: 18%;
        }

        .col-class {
            width: 12%;
        }

        .col-student {
            width: 25%;
        }

        .status-label {
            color: #b91c1c;
            font-weight: 800;
            text-transform: uppercase;
        }

        .success-note {
            padding: 5mm;
            border: 0.35mm solid #bbf7d0;
            border-radius: 2mm;
            background: #f0fdf4;
            color: #166534;
            font-size: 11pt;
            font-weight: 700;
            text-align: center;
        }

        .footer {
            margin-top: 5mm;
            color: #64748b;
            font-size: 7.5pt;
            text-align: right;
        }
    </style>
</head>
<body>
    <main class="summary-page">
        <p class="kicker">SEPP</p>
        <h1>Testzusammenfassung Stundenplan V3</h1>
        <p class="meta">
            @foreach(array_filter([$data['school_name'] ?? null, $data['schoolyear'] ?? null, $data['generated_at'] ?? null]) as $meta)
                <span>{{ $meta }}</span>
            @endforeach
        </p>

        <div class="status-banner">
            @if(($data['failed_count'] ?? 0) > 0)
                {{ $data['failed_count'] }} von {{ $data['tested_count'] }} Tests sind fehlgeschlagen.
                @if(($data['invalid_count'] ?? 0) > 0)
                    {{ $data['invalid_count'] }} Datensätze wurden wegen falscher Daten nicht getestet.
                @endif
            @elseif(($data['invalid_count'] ?? 0) > 0)
                {{ $data['invalid_count'] }} Datensätze wurden wegen falscher Daten nicht getestet.
            @else
                Alle {{ $data['completed_count'] }} Tests wurden erfolgreich abgeschlossen.
            @endif
        </div>

        <table class="summary-counts">
            <tr>
                <td class="summary-count">
                    <span class="summary-label">Geprüft</span>
                    <span class="summary-value">{{ $data['tested_count'] }}</span>
                </td>
                <td class="summary-count summary-count--success">
                    <span class="summary-label">Bestanden</span>
                    <span class="summary-value">{{ $data['passed_count'] }}</span>
                </td>
                <td class="summary-count summary-count--error">
                    <span class="summary-label">Fehlgeschlagen</span>
                    <span class="summary-value">{{ $data['failed_count'] }}</span>
                </td>
                <td class="summary-count summary-count--error">
                    <span class="summary-label">Falsche Daten</span>
                    <span class="summary-value">{{ $data['invalid_count'] }}</span>
                </td>
            </tr>
        </table>

        @if($detailRows->isNotEmpty())
            <h2>Fehlgeschlagene und nicht getestete Datensätze</h2>
            <table class="details-table">
                <thead>
                    <tr>
                        <th class="col-status">Status</th>
                        <th class="col-class">Klasse</th>
                        <th class="col-student">Studierende/r</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detailRows as $student)
                        <tr>
                            <td><span class="status-label">{{ $student['type'] }}</span></td>
                            <td>{{ $student['class_label'] }}</td>
                            <td>{{ $student['student_name'] }}</td>
                            <td>{{ $student['message'] ?: 'Keine weiteren Details.' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="success-note">Es gibt keine fehlgeschlagenen oder übersprungenen Datensätze.</div>
        @endif

        <p class="footer">Erstellt am {{ $data['generated_at'] }}</p>
    </main>
</body>
</html>
