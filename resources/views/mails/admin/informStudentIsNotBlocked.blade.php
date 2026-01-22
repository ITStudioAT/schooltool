<x-mail::message :logo="$logo">
# {{ $data['subject'] }}


<h4>=== SPERRE AUFGEHOBEN ===</h4>

Du wurdest soeben vom Administrator freigeschaltet!<br>
Du kannst dich ab sofort wieder einloggen.

BENUTZER: **{{ $data['full_name']}}**    

E-MAIL: {{ $data['email']  }}



Falls Sie diese E-Mail nicht angefordert haben, brauchen Sie nichts weiter zu tun.

Beste Grüße<br>
{{ $data['from_name']}}
</x-mail::message>