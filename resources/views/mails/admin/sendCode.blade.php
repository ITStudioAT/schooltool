<x-mail::message>
# {{ $data['subject'] }}

Bitte diesen Code eingeben:

<h1>{{ $data['token_2fa'] }}</h1>

Dieser Code läuft in {{ $data['token-expire-time'] }} Minuten ab.

Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>