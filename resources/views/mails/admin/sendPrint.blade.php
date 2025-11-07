<x-mail::message :logo="$logo">
# {{ $data['subject'] }}

Das Sytstem schickt Ihnen angehängte Dateien zur Ihrer Verwendung.


Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>