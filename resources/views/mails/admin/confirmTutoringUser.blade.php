<x-mail::message :logo="$logo">
# {{ $data['subject'] }}


Foldender Benutzer hat sich für Tutoring angemeldet und muss bestätigt werden:

BENUTZER: **{{ $data['full_name']}}**    

E-MAIL: {{ $data['email']  }}


<x-mail::button :url="$data['confirmation_url']">
Benutzer bestätigen
</x-mail::button>


Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>