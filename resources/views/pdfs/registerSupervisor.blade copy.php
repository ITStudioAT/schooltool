<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $data['register_name'] }}</title>

    <style>
        @page {
            margin: 2cm 1cm;
        }

        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        header,
        footer {
            position: fixed;
            left: 0;
            right: 0;
        }

        header {
            top: -40px;
        }

        footer {
            position: fixed;
            bottom: -40px;
            /* keep inside the margin area */
            left: 0;
            right: 0;
            height: 20px;
            border-top: 1px solid #000;
            text-align: right;
            font-size: 10px;
            color: #000;
        }

        footer .page-number:after {
            content: "Seite " counter(page);
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            /* important for PDF */
        }

        tr {
            line-height: 24px;
        }

        .page-break {
            page-break-before: always;
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

    {{-- If supervisor changes, close table, start new page, and show new header --}}
    @if ($lastSupervisor !== null && $currentSupervisor !== $lastSupervisor)
    </tbody>
    </table>

    <div class="page-break"></div>
    @endif

    {{-- If new supervisor OR first supervisor, print group header and table head --}}
    @if ($lastSupervisor === null || $currentSupervisor !== $lastSupervisor)
    <h3 style="margin-bottom: 4px;">
        {{ $data['register_name'] }} – {{ $currentSupervisor }}
    </h3>

    <table style="width:100%; border-collapse:collapse; table-layout: fixed;">
        <colgroup>
            <col style="width:30px;">
            <col style="width:50px">
            <col style="width:100px">
            <col style="width:70px">
            <col style="width:35px">
            <col style="width:70px">
            <col style="width:70px">
            <col style="width:90px">
            <col style="width:70px">
        </colgroup>
        <thead>
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

            {{-- Table row --}}
            <tr>
                <td>{{ substr($booking['register_date']['date'],8,2).'.' .substr($booking['register_date']['date'],5,2) .'.'}}</td>
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
            $lastSupervisor = $currentSupervisor;
            @endphp
            @endforeach

        </tbody>
    </table>

    <footer>
        <div class="page-number"></div>
    </footer>
</body>

</html>