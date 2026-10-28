@if (! empty($pointsAdjustments))
    @php($pointNumber = fn ($number) => rtrim(rtrim(number_format((float) $number, 6, ',', '.'), '0'), ','))
    <div style="margin-top: 12px;">
        <h3>Berechnete Anpassungen</h3>
        <table>
            <thead><tr><th>Semester / Bereich</th><th>Vorher</th><th>Korrektur</th><th>Ergebnis</th><th>Berechnete Semesternote</th></tr></thead>
            <tbody>
                @foreach ($pointsAdjustments as $points)
                    @php($adjustment = $points['adjustment'])
                    <tr>
                        <td>{{ $points['semester'] }} · {{ $points['name'] }}</td>
                        @if ($adjustment['status'] === 'complete')
                            <td>@if (($points['kind'] ?? 'points') === 'grade') Note {{ $pointNumber($adjustment['original']) }} @else {{ $pointNumber($adjustment['original']) }} / {{ $pointNumber($points['maximum']) }} Punkte @endif</td>
                            <td>{{ $adjustment['source'] }}: {{ $adjustment['change'] > 0 ? '+' : '' }}{{ $pointNumber($adjustment['change']) }} {{ ($points['kind'] ?? 'points') === 'grade' ? 'Notenwerte' : 'Punkte' }}
                                @if ($adjustment['change'] !== $adjustment['requested_change'])
                                    (berechnet {{ $adjustment['requested_change'] > 0 ? '+' : '' }}{{ $pointNumber($adjustment['requested_change']) }}, begrenzt)
                                @endif
                            </td>
                            <td>@if (($points['kind'] ?? 'points') === 'grade') Note {{ $pointNumber($adjustment['adjusted']) }} @else {{ $pointNumber($adjustment['adjusted']) }} / {{ $pointNumber($points['maximum']) }} Punkte @endif</td>
                        @else
                            <td colspan="3">Anpassung nicht vollständig berechenbar.</td>
                        @endif
                        <td>{{ $points['semester_result'] ?? 'Nicht berechenbar' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
