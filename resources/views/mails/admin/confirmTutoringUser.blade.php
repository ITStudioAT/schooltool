<x-mail::message :logo="$logo">
# {{ $data['subject'] }}


Foldender Benutzer hat sich angemeldet und muss bestätigt werden:

BENUTZER: **{{ $data['full_name']}}**    

E-MAIL: {{ $data['email']  }}


<x-mail::button :url="$data['confirmation_url']" color="success">
Benutzer bestätigen
</x-mail::button>


oder


<x-mail::button :url="$data['refuse_url']" color="error">
Benutzer ablehnen
</x-mail::button>


Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>