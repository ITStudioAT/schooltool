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
            /* we want the widths from colgroup */
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
    // count per date
    $totals_by_date = [];
    foreach ($data['bookings'] as $b) {
    $d = $b['register_date']['date'] ?? '';
    $totals_by_date[$d] = ($totals_by_date[$d] ?? 0) + 1;
    }
    $lastDate = null;
    @endphp

    @foreach ($data['bookings'] as $booking)
    @php
    $currentDateRaw = $booking['register_date']['date'] ?? '';
    $currentDateDisplay = $currentDateRaw
    ? substr($currentDateRaw, 8, 2) . '.' . substr($currentDateRaw, 5, 2) . '.' . substr($currentDateRaw, 0, 4)
    : '';
    @endphp

    {{-- close previous table + page break when DATE changes --}}
    @if ($lastDate !== null && $currentDateRaw !== $lastDate)
    </tbody>
    </table>
    <div class="page-break"></div>
    @endif

    {{-- start of a DATE block --}}
    @if ($lastDate === null || $currentDateRaw !== $lastDate)
    @php
    $countForThisDate = $totals_by_date[$currentDateRaw] ?? 0;
    @endphp

    <table>
        {{-- widths go here, NOT on the <th> --}}
        <colgroup>
            <col style="width:45px;"> {{-- Dat --}}
            <col style="width:50px;"> {{-- Zeit --}}
            <col style="width:65px;"> {{-- Nachn. Kind --}}
            <col style="width:65px;"> {{-- Vorn. Kind --}}
            <col style="width:50px;"> {{-- Geb.Datum --}}
            <col style="width:65px;"> {{-- Nachname --}}
            <col style="width:65px;"> {{-- Vorname --}}
            <col style="width:110px;"> {{-- E-Mail --}}
            <col style="width:90px;"> {{-- Telefon --}}
        </colgroup>
        <thead>
            <!-- this whole thead repeats on each page -->
            <tr>
                <th colspan="9" style="text-align:left; padding-bottom:4px;">
                    <h3>
                        {{ $data['register_name'] }} – {{ $currentDateDisplay }}
                        ({{ $countForThisDate }} Einträge)
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

            <tr style="border-bottom: 1px dotted #AAA;">
                {{-- you showed supervisor here, keep it or switch back to date --}}
                <td>{{ substr($booking['register_date']['supervisor'] ?? '', 0, 8) }}</td>
                <td>{{ substr($booking['register_date']['from'], 0, 5) . '-' . substr($booking['register_date']['to'], 0, 5) }}</td>
                <td>{{ $booking['student_last_name'] }}</td>
                <td>{{ $booking['student_first_name'] }}</td>
                <td>{{ $booking['student_birthdate'] }}</td>
                <td>{{ $booking['user']['last_name'] }}</td>
                <td>{{ $booking['user']['first_name'] }}</td>
                <td>{{ $booking['user']['email'] }}</td>
                <td>{{ $booking['user']['phone'] }}</td>
            </tr>

            @php
            $lastDate = $currentDateRaw;
            @endphp
            @endforeach

            @if (!empty($data['bookings']))
        </tbody>
    </table>
    @endif

    <p><strong>Gesamt:</strong> {{ $data['total_count'] }} Einträge</p>

</body>

</html>