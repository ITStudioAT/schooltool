<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>{{ $data['register_name'] }}</title>
    <style>
        @page {
            margin: 2cm 1cm;
        }

        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            /* use colgroup widths */
        }

        th,
        td {
            font-size: 10px;
            text-align: left;
            padding: 1px 2px;
            word-wrap: break-word;
            word-break: break-all;
        }

        thead {
            display: table-header-group;
            /* repeat on each page */
        }

        tr {
            line-height: 20px;
        }

        .page-break {
            page-break-before: always;
        }

        h3 {
            margin: 0;
            font-size: 13px;
        }
    </style>
</head>

<body>

    @php
    $lastSupervisor = null;
    @endphp

    @foreach ($data['bookings'] as $booking)
    @php
    $currentSupervisor = $booking['register_date']['supervisor'] ?? '';
    @endphp

    {{-- when supervisor changes: close previous table + page break --}}
    @if ($lastSupervisor !== null && $currentSupervisor !== $lastSupervisor)
    </tbody>
    </table>
    <div class="page-break"></div>
    @endif

    {{-- start of a supervisor block --}}
    @if ($lastSupervisor === null || $currentSupervisor !== $lastSupervisor)
    @php
    $countForThisSupervisor = $data['totals_by_supervisor'][$currentSupervisor] ?? 0;
    @endphp

    <table>
        <colgroup>
            <col style="width:30px;"> {{-- Dat --}}
            <col style="width:55px;"> {{-- Zeit --}}
            <col style="width:65px;"> {{-- Nachn. Kind --}}
            <col style="width:65px;"> {{-- Vorn. Kind --}}
            <col style="width:50px;"> {{-- Geb.Datum --}}
            <col style="width:65px;"> {{-- Nachname --}}
            <col style="width:65px;"> {{-- Vorname --}}
            <col style="width:110px;"> {{-- E-Mail --}}
            <col style="width:90px;"> {{-- Telefon --}}
        </colgroup>
        <thead>
            <!-- this header (with supervisor + count) will repeat on page break -->
            <tr>
                <th colspan="9" style="text-align:left; padding-bottom:4px;">
                    <h3>
                        {{ $data['register_name'] }} – {{ $currentSupervisor }}
                        ({{ $countForThisSupervisor }} Einträge)
                    </h3>
                </th>
            </tr>
            <tr style="border-bottom: 1px solid #000; font-weight: bold;">
                <th>Dat</th>
                <th>Zeit</th>
                <th>Nachn. Kind</th>
                <th>Vorn. Kind</th>
                <th>Geb.Datum</th>
                <th>Nachname</th>
                <th>Vorname</th>
                <th>E-Mail</th>
                <th>Telefon</th>
            </tr>
        </thead>
        <tbody>
            @endif

            {{-- primary child row --}}
            <tr style="border-bottom: {{ empty($booking['siblings']) ? '1px dotted #AAA' : 'none' }};">
                <td>{{ substr($booking['register_date']['date'], 8, 2) . '.' . substr($booking['register_date']['date'], 5, 2) . '.' }}</td>
                <td>{{ substr($booking['register_date']['from'], 0, 5) . '-' . substr($booking['register_date']['to'], 0, 5) }}</td>
                <td>{{ $booking['student_last_name'] }}</td>
                <td>{{ $booking['student_first_name'] }}</td>
                <td>{{ $booking['student_birthdate'] }}</td>
                <td>{{ $booking['user']['last_name'] }}</td>
                <td>{{ $booking['user']['first_name'] }}</td>
                <td>{{ $booking['user']['email'] }}</td>
                <td>{{ $booking['user']['phone'] }}</td>
            </tr>
            {{-- sibling rows --}}
            @foreach ($booking['siblings'] ?? [] as $si => $sibling)
            <tr style="border-bottom: {{ $si === count($booking['siblings']) - 1 ? '1px dotted #AAA' : 'none' }}; color: #555; font-style: italic;">
                <td></td>
                <td></td>
                <td>↳ {{ $sibling['last_name'] ?? '' }}</td>
                <td>{{ $sibling['first_name'] ?? '' }}</td>
                <td>{{ $sibling['birthdate'] ?? '' }}</td>
                <td colspan="4"></td>
            </tr>
            @endforeach

            @php
            $lastSupervisor = $currentSupervisor;
            @endphp
            @endforeach

            {{-- close last table if there were bookings --}}
            @if (!empty($data['bookings']))
        </tbody>
    </table>
    @endif

    <p><strong>Gesamt:</strong> {{ $data['total_count'] }} Einträge</p>

</body>

</html>