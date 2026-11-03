<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Signal Null · Auswertung</title>
    <style>
        @page { margin: 16mm 14mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #18382e; }
        h1 { font-size: 21pt; margin: 0 0 5mm; color: #17654b; }
        h2 { font-size: 13pt; margin: 0 0 3mm; }
        .metadata { line-height: 1.6; margin-bottom: 6mm; }
        .muted { color: #52675f; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th, td { padding: 3mm 2mm; border-bottom: 1px solid #d8e5dd; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        th { background: #eaf4ee; font-size: 9pt; }
        tr { page-break-inside: avoid; }
        .participants { font-size: 9pt; color: #52675f; }
        .notes { font-size: 9pt; line-height: 1.5; margin-top: 5mm; }
        footer { position: fixed; bottom: -10mm; font-size: 8pt; color: #52675f; }
        .page::after { content: counter(page); }
    </style>
</head>
<body>
@php
    $session = $report['session'];
    $final = $session['status'] === 'ended';
    $ranking = $report['ranking'];
    $ranks = ($ranking['final'] ?? false) ? collect($ranking['entries'])->keyBy('id') : collect();
    $runs = collect($report['runs'])->keyBy('id');
    $orderedRuns = $ranks->isNotEmpty() ? $ranks->keys()->map(fn ($id) => $runs->get($id)) : $runs->values();
    $withTime = $report['clock']['mode'] !== 'none';
    $states = ['waiting' => 'Warteraum', 'running' => 'Runde läuft', 'paused' => 'Pausiert', 'ended' => 'Beendet'];
    $levels = [1 => 'Leicht', 2 => 'Mittel', 3 => 'Anspruchsvoll'];
@endphp
<footer>Signal Null · {{ $exportedAt }} (Europe/Vienna) · Seite <span class="page"></span></footer>
<h1>Signal Null · {{ $final ? 'Abschlussauswertung' : 'Zwischenstand' }}</h1>
<h2>{{ $session['title'] }}</h2>
<div class="metadata">
    {{ $courseTitle ?: 'Externe Runde' }} · {{ $states[$session['status']] }} · {{ $session['mode'] === 'teams' ? 'Teams' : 'Einzeln' }}<br>
    {{ $session['planned_minutes'] }} Minuten geplant · {{ $levels[$session['difficulty']] }} · Stand: {{ $exportedAt }} (Europe/Vienna)
</div>
@if ($orderedRuns->isEmpty())
    <p>Noch keine Teilnehmenden beigetreten.</p>
@else
    <table>
        <thead><tr>
            @if ($ranks->isNotEmpty())<th style="width:5%">Rang</th>@endif
            <th style="width:29%">Person / Team</th>
            <th style="width:24%">Bereich / Abschluss</th>
            <th>Bestätigt</th><th>Ohne Hilfe</th><th>Mit Hilfe</th>
            @if ($withTime)<th>Aktive Zeit</th>@endif
        </tr></thead>
        <tbody>
        @foreach ($orderedRuns as $run)
            <tr>
                @if ($ranks->isNotEmpty())<td>{{ $ranks->get($run['id'])['rank'] }}</td>@endif
                <td><strong>{{ $run['team_name'] ?: ($run['members'][0] ?? 'Spielweg') }}</strong>
                    @if ($run['team_name'])<div class="participants">{{ implode(', ', $run['members']) }}</div>@endif
                </td>
                <td>{{ $run['completed'] ? 'Verbindung wiederhergestellt' : $run['chapter'] }}</td>
                <td>{{ $run['solved'] }} / {{ $run['total'] }}</td>
                <td>{{ $run['solved'] - $run['with_help'] }}</td><td>{{ $run['with_help'] }}</td>
                @if ($withTime)<td>{{ sprintf('%d:%02d', intdiv($run['clock']['elapsed_seconds'], 60), $run['clock']['elapsed_seconds'] % 60) }} min</td>@endif
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
<div class="notes">
    @if ($ranking)
        <p>{{ $ranking['rules'] }}</p>
        @if (! $final)<p>Die Reihung wird erst nach dem Beenden der Runde angezeigt.</p>@endif
    @else
        <p>Die Reihung ist für diese Runde deaktiviert.</p>
    @endif
    <p>„Mit Hilfe“ beschreibt den Lernweg und zieht keine Punkte ab. Die Auswertung vergibt keine Noten.
        @if ($withTime)Aktive Zeiten zählen seit dem gemeinsamen Rundenstart; Pausen sind ausgeschlossen.@endif
        @if ($report['clock']['mode'] === 'countdown')Der Ablauf des Countdowns beendet das Spiel nicht automatisch.@endif
    </p>
</div>
</body>
</html>
