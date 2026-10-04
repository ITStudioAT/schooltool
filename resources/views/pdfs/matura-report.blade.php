<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 15mm 12mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #182d38; font-size: 8pt; line-height: 1.35; }
        h1 { font-size: 23pt; margin: 0 0 3mm; }
        h2 { font-size: 13pt; margin: 7mm 0 3mm; }
        .eyebrow { color: #117b73; font-size: 9pt; letter-spacing: 2px; font-weight: bold; }
        .meta, .muted { color: #526672; }
        .summary { background: #edf6f4; padding: 4mm; margin: 5mm 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { text-align: left; background: #183f47; color: white; padding: 2.5mm 1.5mm; font-size: 7pt; }
        td { padding: 2.5mm 1.5mm; border-bottom: 1px solid #dce5e8; vertical-align: top; overflow-wrap: break-word; }
        .visits td:nth-child(3), .visits td:nth-child(4), .visits td:nth-child(5), .visits td:nth-child(6) { font-size: 7pt; white-space: nowrap; }
        .small { font-size: 6.7pt; }
        .page { page-break-before: always; }
        .note { border-left: 3px solid #b88c44; padding-left: 3mm; }
        .footer { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #607780; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
<div class="footer">00-Manager · Vertrauliches Prüfungsprotokoll <span style="float:right">Seite <span class="page-number"></span></span></div>
@php
    $time = fn ($value) => $value ? \Carbon\Carbon::parse($value)->timezone('Europe/Vienna')->format('d.m. H:i:s') : '—';
    $duration = fn ($seconds) => $seconds === null ? '—' : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
@endphp
<div class="eyebrow">SCHOOLTOOL · MATURA</div>
<h1>00-Manager</h1>
<strong>{{ $session->name }}</strong>
<div class="meta">Prüfung: {{ $session->exam_date->format('d.m.Y') }} · Stand: {{ $time($generated_at) }} · Zeitzone: Europe/Vienna</div>
@if($filters)
    <p>Gefilterte Auswertung:
        @if(!empty($filters['room_id'])) Raum {{ $rows->first()['room_name'] ?? $filters['room_id'] }} @endif
        @if(!empty($filters['student_id'])) · {{ $rows->first()['student_name'] ?? 'Ausgewählter Schüler' }} @endif
        @if(!empty($filters['status'])) · {{ \App\Services\Matura\MaturaReportService::StatusLabels[$filters['status']] }} @endif
    </p>
@endif
<div class="summary">
    <strong>{{ $summary['actual_visits'] }} tatsächliche Toilettengänge</strong> ·
    {{ $summary['open'] }} offen · {{ $summary['cancelled'] }} storniert / Fehlbuchung ·
    Toilettenzeit gesamt {{ $duration($summary['toilet_seconds']) }} min:sek
    ({{ $summary['completed_durations'] }} abgeschlossene Zeitmessungen)
</div>
<p class="note">Ein Toilettengang zählt erst ab bestätigtem Eintritt. „—“ bedeutet: Zeitpunkt oder abgeschlossene Dauer liegt nicht vor.
    Laufende Gänge bleiben offen. Fehlbuchungen bleiben im Protokoll erhalten und zählen nicht als tatsächliche Gänge. Alle Dauern in min:sek.</p>
<h2>Einzelne Gänge</h2>
<table class="visits">
    <thead><tr>
        <th style="width:18%">Schüler / Raum</th><th style="width:13%">Status</th>
        <th style="width:12%">Anforderung<br>Freigabe</th><th style="width:12%">Abgang<br>Ankunft</th>
        <th style="width:12%">Toilette ein<br>Toilette aus</th><th style="width:11%">Rückkehr</th>
        <th style="width:10%">Toilette<br>Abwesend</th><th style="width:12%">Wartezeit<br>Raum / Station</th>
    </tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            <td><strong>{{ $row['student_name'] }}</strong><br>{{ $row['class_name'] }} · {{ $row['room_name'] }} <span class="small">#{{ $row['id'] }}</span></td>
            <td>{{ $row['status_label'] }}@if($row['correction_reason'])<br><span class="small">{{ $row['correction_reason'] }}</span>@endif</td>
            <td>{{ $time($row['requested_at']) }}<br>{{ $time($row['approved_at']) }}</td>
            <td>{{ $time($row['departed_at']) }}<br>{{ $time($row['arrived_at']) }}</td>
            <td>{{ $time($row['entered_at']) }}<br>{{ $time($row['exited_at']) }}</td>
            <td>{{ $time($row['returned_at']) }}</td>
            <td>{{ $duration($row['toilet_seconds']) }}<br>{{ $duration($row['absence_seconds']) }}</td>
            <td>{{ $duration($row['room_wait_seconds']) }} / {{ $duration($row['station_wait_seconds']) }}</td>
        </tr>
    @empty
        <tr><td colspan="8">Für diese Auswahl sind noch keine Gänge protokolliert.</td></tr>
    @endforelse
    </tbody>
</table>
<h2>Nach Raum</h2>
<table>
    <thead><tr><th>Raum</th><th>Tatsächliche Gänge</th><th>Offen</th><th>Toilettenzeit</th><th>Ø Toilettenzeit</th><th>Abwesenheit</th></tr></thead>
    <tbody>@foreach($rooms as $room)<tr><td>{{ $room['name'] }}</td><td>{{ $room['actual_visits'] }}</td><td>{{ $room['open'] }}</td><td>{{ $duration($room['toilet_seconds']) }}</td><td>{{ $duration($room['average_toilet_seconds']) }}</td><td>{{ $duration($room['absence_seconds']) }}</td></tr>@endforeach</tbody>
</table>
<h2>Nach Schüler</h2>
<table>
    <thead><tr><th>Schüler</th><th>Raum</th><th>Tatsächliche Gänge</th><th>Offen</th><th>Toilettenzeit</th><th>Abwesenheit</th></tr></thead>
    <tbody>@foreach($students as $student)<tr><td>{{ $student['name'] }}</td><td>{{ $student['room'] }}</td><td>{{ $student['actual_visits'] }}</td><td>{{ $student['open'] }}</td><td>{{ $duration($student['toilet_seconds']) }}</td><td>{{ $duration($student['absence_seconds']) }}</td></tr>@endforeach</tbody>
</table>
<div class="page"></div>
<div class="eyebrow">00-MANAGER · EREIGNISPROTOKOLL</div>
<h2>{{ $session->name }}</h2>
<table>
    <thead><tr><th style="width:14%">Zeit</th><th style="width:22%">Aufsicht</th><th style="width:9%">Gang</th><th style="width:25%">Aktion</th><th style="width:30%">Hinweis</th></tr></thead>
    <tbody>@foreach($events as $event)<tr>
        <td>{{ $time($event->occurred_at) }}</td><td>{{ $event->actor }}</td><td>{{ $event->matura_visit_id ?? '—' }}</td>
        <td>{{ \App\Services\Matura\MaturaReportService::EventLabels[$event->action] ?? $event->action }}</td><td>{{ $event->details['reason'] ?? $event->details['name'] ?? '—' }}</td>
    </tr>@endforeach</tbody>
</table>
</body>
</html>
