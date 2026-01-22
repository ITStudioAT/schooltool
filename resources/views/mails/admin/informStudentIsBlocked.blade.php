<x-mail::message :logo="$logo">
# {{ $data['subject'] }}


<h4>=== SPERRE ===</h4>

Du wurdest soeben vom Administrator blockiert!<br>
Du kannst dich nicht mehr einloggen.

BENUTZER: **{{ $data['full_name']}}**    

E-MAIL: {{ $data['email']  }}


Melde Dich bitte umgehend beim Administrator!


Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>