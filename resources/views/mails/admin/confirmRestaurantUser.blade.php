<x-mail::message :logo="$logo">
# {{ $data['subject'] }}

Folgender Benutzer hat sich fuer das Restaurant registriert und wartet auf die Freischaltung:

BENUTZER: **{{ $data['full_name'] }}**

E-MAIL: {{ $data['email'] }}

<x-mail::button :url="$data['confirmation_url']" color="success">
Benutzer bestaetigen
</x-mail::button>

oder

<x-mail::button :url="$data['refuse_url']" color="error">
Benutzer ablehnen
</x-mail::button>

Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Gruesse<br>
{{ $data['from_name'] }}
</x-mail::message>
