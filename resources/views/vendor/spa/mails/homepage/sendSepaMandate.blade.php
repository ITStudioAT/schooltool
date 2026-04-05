<x-mail::message :logo="$logo">
# {{ $data['subject'] }}

Anbei erhalten Sie das bestätigte SEPA-Lastschriftmandat als PDF.

Die Datei ist dieser E-Mail als Anhang beigefügt.

Beste Grüße<br>
{{ $data['from_name'] }}
</x-mail::message>
