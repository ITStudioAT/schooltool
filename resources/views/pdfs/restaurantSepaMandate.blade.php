<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $mandate['title'] }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @page {
            margin: 0;
            size: A4 portrait;
        }

        body {
            margin: 0;
            padding: 15mm 17mm;
            font-family: DejaVu Sans, sans-serif;
            color: #1a1a1a;
            background: #ffffff;
            font-size: 10.5px;
            line-height: 1.34;
        }

        h1 {
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 11px;
            padding-bottom: 4px;
            border-bottom: 2px solid #1a5276;
        }

        h2 {
            font-size: 12px;
            font-weight: 700;
            color: #1a1a1a;
            margin-top: 10px;
            margin-bottom: 4px;
        }

        /* Form table: label left, value right in blue box */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .form-table td {
            padding: 2.5px 0;
            vertical-align: top;
        }

        .form-table .label-cell {
            width: 42%;
            font-size: 10pt;
            color: #333;
            padding-right: 8px;
        }

        .form-table .value-cell {
            width: 58%;
            background: #dce6f1;
            padding: 3px 6px;
            font-size: 10.5pt;
            line-height: 1.24;
            font-weight: 600;
            color: #111;
            border: 1px solid #c5d3e2;
        }

        /* Payee section: full-width value box */
        .payee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3px;
        }

        .payee-table td {
            vertical-align: top;
            padding: 2.5px 0;
        }

        .payee-table .label-cell {
            width: 42%;
            font-size: 10pt;
            color: #333;
            padding-right: 8px;
        }

        .payee-table .value-cell {
            width: 58%;
            background: #dce6f1;
            padding: 4px 6px;
            font-size: 10.5pt;
            line-height: 1.24;
            font-style: italic;
            font-weight: 600;
            color: #111;
            border: 1px solid #c5d3e2;
        }

        .payee-table .value-cell * {
            font-size: 10.5pt;
            line-height: 1.24;
        }

        .payee-table .value-cell p {
            margin: 0 0 2px;
        }

        .payee-table .value-cell p:last-child {
            margin-bottom: 0;
        }

        /* Zahlungsart */
        .payment-type-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-type-table td {
            vertical-align: middle;
            padding: 2.5px 0;
        }

        .payment-type-table .label-cell {
            width: 42%;
            font-size: 10pt;
            font-weight: 700;
            color: #1a1a1a;
        }

        .payment-type-table .value-cell {
            width: 58%;
            font-size: 10.5pt;
            color: #1a1a1a;
        }

        .payment-type-table .payment-choice {
            display: block;
        }

        /* SEPA-Ermaechtigung text */
        .mandate-text {
            margin-top: 3px;
            font-size: 10pt;
            line-height: 1.32;
            color: #222;
        }

        .mandate-text p {
            margin: 0 0 4px;
        }

        .mandate-text p:last-child {
            margin-bottom: 0;
        }

        /* Signature row */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .sig-table td {
            vertical-align: top;
            padding-right: 10px;
        }

        .sig-table td:last-child {
            padding-right: 0;
        }

        .sig-label {
            font-size: 10pt;
            color: #555;
            margin-bottom: 3px;
        }

        .sig-box {
            background: #dce6f1;
            border: 1px solid #c5d3e2;
            min-height: 30px;
            padding: 4px 6px;
            font-size: 10.5pt;
            line-height: 1.2;
            font-weight: 600;
            color: #111;
            word-break: break-word;
        }

        .sig-meta {
            margin-top: 3px;
            font-size: 10.5pt;
            font-weight: 700;
            color: #0f766e;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
            font-size: 10.5pt;
            word-break: break-all;
        }
    </style>
</head>
<body>

    <h1>SEPA-Lastschriftmandat</h1>

    {{-- Zahlungsempfaenger --}}
    <h2>Angaben zum Zahlungsempf&auml;nger (Gl&auml;ubiger)</h2>
    <table class="payee-table">
        <tr>
            <td class="label-cell">Name / offizielle Bezeichnung des Zahlungsempf&auml;ngers</td>
            <td class="value-cell">{!! $mandate['sepa_payee'] !== '' ? $mandate['sepa_payee'] : 'Nicht hinterlegt' !!}</td>
        </tr>
    </table>

    {{-- Zahlungspflichtiger --}}
    <h2>Angaben zum Zahlungspflichtigen (Debitor / Kontoinhaber)</h2>
    <table class="form-table">
        <tr>
            <td class="label-cell">Name des Kontoinhabers (Vor- und Nachname)</td>
            <td class="value-cell">{{ $mandate['account_holder_name'] }}</td>
        </tr>
        <tr>
            <td class="label-cell">Anschrift (Stra&szlig;e, Hausnummer, PLZ, Ort)</td>
            <td class="value-cell">{{ $mandate['address_line'] }}@if($mandate['postal_code'] || $mandate['city']), {{ $mandate['postal_code'] }} {{ $mandate['city'] }}@endif</td>
        </tr>
        <tr>
            <td class="label-cell">Land</td>
            <td class="value-cell">{{ $mandate['country'] ?: 'Österreich' }}</td>
        </tr>
        <tr>
            <td class="label-cell">IBAN</td>
            <td class="value-cell">{{ $mandate['iban'] }}</td>
        </tr>
        <tr>
            <td class="label-cell">BIC (optional im SEPA-Raum)</td>
            <td class="value-cell">{{ $mandate['bic'] !== '' ? $mandate['bic'] : '' }}</td>
        </tr>
    </table>

    {{-- Kind --}}
    <h2>Angaben zum Kind</h2>
    <table class="form-table">
        @forelse($mandate['child_entries'] as $child)
            <tr>
                <td class="label-cell">Name, Klasse des Kindes</td>
                <td class="value-cell">{{ $child['name'] }}@if($child['schoolclass'] !== ''), {{ $child['schoolclass'] }}@endif</td>
            </tr>
        @empty
            <tr>
                <td class="label-cell">Name, Klasse des Kindes</td>
                <td class="value-cell"></td>
            </tr>
        @endforelse
    </table>

    {{-- Zahlungsart --}}
    <table class="payment-type-table" style="margin-top: 10px;">
        <tr>
            <td class="label-cell">Zahlungsart</td>
            <td class="value-cell">
                <span class="payment-choice">&#9744; Einmalige Zahlung</span>
                <span class="payment-choice">&#9746; Wiederkehrende Zahlung</span>
            </td>
        </tr>
    </table>

    {{-- SEPA-Ermaechtigung --}}
    <h2>SEPA-Erm&auml;chtigung</h2>
    <div class="mandate-text">
        @if($mandate['sepa_mandate_text'] !== '')
            {!! $mandate['sepa_mandate_text'] !!}
        @else
            <p>Nicht hinterlegt</p>
        @endif
    </div>

    {{-- Unterschrift --}}
    <table class="sig-table">
        <tr>
            <td style="width: 28%;">
                <div class="sig-label">Ort</div>
                <div class="sig-box">{{ $mandate['signature_location'] ?: '' }}</div>
            </td>
            <td style="width: 28%;">
                <div class="sig-label">Datum</div>
                <div class="sig-box">{{ $mandate['confirmed_at_label'] }}</div>
            </td>
            <td style="width: 44%;">
                <div class="sig-label">Unterschrift Kontoinhaber/in</div>
                <div class="sig-box">
                    <span class="mono">{{ $mandate['signature_uuid'] }}</span>
                    <div class="sig-meta">Online best&auml;tigt</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
