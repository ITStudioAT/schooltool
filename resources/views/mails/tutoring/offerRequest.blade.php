<x-mail::message :logo="$logo">
# {{ $data['subject'] }}

Du hast eine neue Nachhilfe-Anfrage erhalten.<br>
Um die Anfrage zu öffnen, klicke einfach auf folgenden Button:


<x-mail::button :url="$data['data']['url']">
Anfrage öffnen
</x-mail::button>


<br><br>

Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>