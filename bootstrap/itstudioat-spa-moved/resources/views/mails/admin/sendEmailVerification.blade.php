<x-mail::message>
# {{ $data['subject'] }}

Zur E-Mail-Verifikation klicken Sie bitte auf folgenden Button:

<x-mail::button :url="$data['url']">
E-Mail-Verifikation
</x-mail::button>


Dieser Code läuft in {{ $data['token-expire-time'] }} Minuten ab.

Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>